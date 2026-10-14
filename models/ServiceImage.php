<?php
require_once __DIR__ . '/GalleryImage.php';

// Extra photos for a service; service.image stays the main photo.
class ServiceImage extends GalleryImage {
    protected $table = 'service_image';
    protected $idColumn = 'service_image_id';
    protected $ownerColumn = 'service_id';
}
