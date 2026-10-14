<?php
/*
    Extra photos for a catalog item (the details page gallery). The item's own image column
    remains the main photo; these rows are the additional angles, shown after it in sort_order.
    Subclasses name the table and columns (PartImage, ServiceImage).
*/
abstract class GalleryImage {
    const MAX_PER_ITEM = 8;

    protected $conn;
    protected $table;
    protected $idColumn;
    protected $ownerColumn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function listForItem($itemId) {
        $stmt = $this->conn->prepare("
            SELECT {$this->idColumn} AS image_id, image, sort_order
            FROM {$this->table}
            WHERE {$this->ownerColumn} = :owner_id
            ORDER BY sort_order ASC, {$this->idColumn} ASC
        ");
        $stmt->bindValue(':owner_id', intval($itemId), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Extra photos for several items at once, keyed by the item's id.
    public function listForItems(array $itemIds) {
        $itemIds = array_values(array_unique(array_map('intval', $itemIds)));

        if (empty($itemIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        $stmt = $this->conn->prepare("
            SELECT {$this->idColumn} AS image_id, {$this->ownerColumn} AS owner_id, image, sort_order
            FROM {$this->table}
            WHERE {$this->ownerColumn} IN ($placeholders)
            ORDER BY sort_order ASC, {$this->idColumn} ASC
        ");
        $stmt->execute($itemIds);

        $grouped = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $grouped[$row['owner_id']][] = $row;
        }

        return $grouped;
    }

    public function countForItem($itemId) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM {$this->table} WHERE {$this->ownerColumn} = :owner_id");
        $stmt->bindValue(':owner_id', intval($itemId), PDO::PARAM_INT);
        $stmt->execute();

        return intval($stmt->fetchColumn());
    }

    // Appends photos after the item's current last one.
    public function addMany($itemId, array $images) {
        if (empty($images)) {
            return;
        }

        $stmt = $this->conn->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM {$this->table} WHERE {$this->ownerColumn} = :owner_id");
        $stmt->bindValue(':owner_id', intval($itemId), PDO::PARAM_INT);
        $stmt->execute();
        $nextOrder = intval($stmt->fetchColumn()) + 1;

        $insert = $this->conn->prepare("
            INSERT INTO {$this->table} ({$this->ownerColumn}, image, sort_order)
            VALUES (:owner_id, :image, :sort_order)
        ");

        foreach ($images as $image) {
            $insert->bindValue(':owner_id', intval($itemId), PDO::PARAM_INT);
            $insert->bindValue(':image', $image);
            $insert->bindValue(':sort_order', $nextOrder++, PDO::PARAM_INT);
            $insert->execute();
        }
    }

    // Saves the order typed in the admin form ([image id => position]), scoped to this item.
    public function updateOrder($itemId, array $orderById) {
        $stmt = $this->conn->prepare("
            UPDATE {$this->table}
            SET sort_order = :sort_order
            WHERE {$this->idColumn} = :image_id AND {$this->ownerColumn} = :owner_id
        ");

        foreach ($orderById as $imageId => $position) {
            $stmt->bindValue(':sort_order', max(0, intval($position)), PDO::PARAM_INT);
            $stmt->bindValue(':image_id', intval($imageId), PDO::PARAM_INT);
            $stmt->bindValue(':owner_id', intval($itemId), PDO::PARAM_INT);
            $stmt->execute();
        }
    }

    // Deletes the given photos of this item and returns their file names so the files can be removed.
    public function removeMany($itemId, array $imageIds) {
        $imageIds = array_values(array_unique(array_map('intval', $imageIds)));

        if (empty($imageIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($imageIds), '?'));
        $params = array_merge([intval($itemId)], $imageIds);

        $select = $this->conn->prepare("SELECT image FROM {$this->table} WHERE {$this->ownerColumn} = ? AND {$this->idColumn} IN ($placeholders)");
        $select->execute($params);
        $files = $select->fetchAll(PDO::FETCH_COLUMN);

        $delete = $this->conn->prepare("DELETE FROM {$this->table} WHERE {$this->ownerColumn} = ? AND {$this->idColumn} IN ($placeholders)");
        $delete->execute($params);

        return $files;
    }
}
