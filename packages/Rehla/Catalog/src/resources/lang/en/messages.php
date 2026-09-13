<?php

declare(strict_types=1);

return [
    'validation' => [
        'bilingual_content' => 'Complete English and Arabic service content is required.',
        'media' => 'Bilingual media alt text and a positive sort order are required.',
        'name_length' => 'Service names may not exceed 100 characters.',
        'pagination' => 'The catalog page or page size is invalid.',
        'price_different' => 'The new service price must be different.',
        'price_positive' => 'The service price must be positive.',
        'requirement' => 'A bilingual requirement and positive sort order are required.',
        'requirements_media' => 'At least one requirement and one media item are required.',
        'row_integer' => 'A catalog database field must be an integer.',
        'row_string' => 'A catalog database field must be text.',
        'service_order' => 'A unique service order is required.',
        'slug_price_sort' => 'The service slug, price, or sort order is invalid.',
        'unique_sort' => 'Sort orders must be unique.',
    ],
];
