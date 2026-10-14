<?php
require_once __DIR__ . '/GalleryImage.php';

// Extra photos for a part; part.image stays the main photo.
class PartImage extends GalleryImage {
    protected $table = 'part_image';
    protected $idColumn = 'part_image_id';
    protected $ownerColumn = 'part_id';
}
