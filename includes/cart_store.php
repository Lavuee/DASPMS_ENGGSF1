<?php
/*
    Saved customer cart (table cart_item).

    $_SESSION['cart'] stays the working copy that the cart page, header count and
    checkout read. This file keeps a copy per customer in the database so the cart
    survives logging out and follows the account to other devices:
      - cartSaveToDb()       after every cart change and after checkout
      - cartRestoreFromDb()  right after a customer logs in

    Both fail quietly (logged with error_log) so a cart problem never blocks
    logging in, browsing or checking out.
*/

// The logged-in customer's id: from the session, or looked up from the user id.
function cartCustomerId(PDO $db) {
    if (!empty($_SESSION['customer_id'])) {
        return intval($_SESSION['customer_id']);
    }

    if (empty($_SESSION['user_id'])) {
        return 0;
    }

    $stmt = $db->prepare("SELECT customer_id FROM customer WHERE user_id = ? LIMIT 1");
    $stmt->execute([intval($_SESSION['user_id'])]);
    $customerId = intval($stmt->fetchColumn());

    if ($customerId > 0) {
        $_SESSION['customer_id'] = $customerId;
    }

    return $customerId;
}

// Replaces the customer's saved cart with what is in the session right now.
function cartSaveToDb(PDO $db) {
    try {
        $customerId = cartCustomerId($db);

        if ($customerId <= 0) {
            return;
        }

        $cart = (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) ? $_SESSION['cart'] : [];
        $ownTransaction = !$db->inTransaction();

        if ($ownTransaction) {
            $db->beginTransaction();
        }

        $db->prepare("DELETE FROM cart_item WHERE customer_id = ?")->execute([$customerId]);

        $insert = $db->prepare("INSERT INTO cart_item (customer_id, part_id, quantity) VALUES (?, ?, ?)");

        foreach ($cart as $partId => $item) {
            $quantity = intval($item['qty'] ?? 0);

            if (intval($partId) > 0 && $quantity > 0) {
                $insert->execute([$customerId, intval($partId), $quantity]);
            }
        }

        if ($ownTransaction) {
            $db->commit();
        }
    } catch (Exception $e) {
        if (isset($ownTransaction) && $ownTransaction && $db->inTransaction()) {
            $db->rollBack();
        }

        error_log('Saved cart: could not save - ' . $e->getMessage());
    }
}

/*
    Loads the saved cart into the session after login. Names, prices, stock and photos
    are read fresh from the part table, archived parts are dropped, and quantities above
    the current stock are lowered to what is available. Anything already in the session
    cart is kept. The cleaned result is saved back.
*/
function cartRestoreFromDb(PDO $db, $customerId) {
    try {
        $customerId = intval($customerId);

        if ($customerId <= 0) {
            return;
        }

        $stmt = $db->prepare("
            SELECT
                ci.part_id,
                ci.quantity,
                p.part_name,
                p.unit_price,
                p.quantity_on_hand,
                p.image
            FROM cart_item ci
            JOIN part p ON p.part_id = ci.part_id
            WHERE ci.customer_id = ?
              AND p.is_active = 1
            ORDER BY ci.added_at ASC
        ");
        $stmt->execute([$customerId]);

        $cart = (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) ? $_SESSION['cart'] : [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $partId = intval($row['part_id']);
            $stock = intval($row['quantity_on_hand']);
            $quantity = min(intval($row['quantity']) + intval($cart[$partId]['qty'] ?? 0), $stock);

            if ($quantity <= 0) {
                unset($cart[$partId]);
                continue;
            }

            $cart[$partId] = [
                'part_id' => $partId,
                'name' => $row['part_name'],
                'price' => floatval($row['unit_price']),
                'qty' => $quantity,
                'stock' => $stock,
                'image' => $row['image']
            ];
        }

        $_SESSION['cart'] = $cart;

        if (empty($_SESSION['cart'])) {
            unset($_SESSION['cart']);
        }

        cartSaveToDb($db);
    } catch (Exception $e) {
        error_log('Saved cart: could not restore - ' . $e->getMessage());
    }
}
