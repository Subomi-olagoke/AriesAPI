<?php

return [
    /**
     * Cloudinary cloud URL. This can be found in your Cloudinary dashboard.
     * Format: cloudinary://{api_key}:{api_secret}@{cloud_name}
     */
    'cloud_url' => env('CLOUDINARY_URL'),

    /**
     * Upload presets to use for different types of uploads
     */
    'upload_presets' => [
        'avatar' => env('CLOUDINARY_AVATAR_PRESET', 'avatars'),
        'post_image' => env('CLOUDINARY_POST_IMAGE_PRESET', 'post_images'),
        'post_video' => env('CLOUDINARY_POST_VIDEO_PRESET', 'post_videos'),
        'post_thumbnail' => env('CLOUDINARY_POST_THUMBNAIL_PRESET', 'post_thumbnails'),
        'course_image' => env('CLOUDINARY_COURSE_IMAGE_PRESET', 'course_images'),
        'course_video' => env('CLOUDINARY_COURSE_VIDEO_PRESET', 'course_videos'),
        'course_thumbnail' => env('CLOUDINARY_COURSE_THUMBNAIL_PRESET', 'course_thumbnails'),
        'lesson_image' => env('CLOUDINARY_LESSON_IMAGE_PRESET', 'lesson_images'),
        'lesson_video' => env('CLOUDINARY_LESSON_VIDEO_PRESET', 'lesson_videos'),
        'lesson_thumbnail' => env('CLOUDINARY_LESSON_THUMBNAIL_PRESET', 'lesson_thumbnails'),
        'message_attachment' => env('CLOUDINARY_MESSAGE_ATTACHMENT_PRESET', 'message_attachments'),
        'readlist_images' => env('CLOUDINARY_READLIST_IMAGES_PRESET', 'readlist_images'),
    ],
    
    /**
     * Default transformations for different asset types
     */
    'transformations' => [
        'avatar' => [
            'width' => 120,
            'height' => 120,
            'crop' => 'fill',
            'gravity' => 'face',
            'quality' => 'auto'
        ],
        'course_thumbnail' => [
            'width' => 800,
            'height' => 450,
            'crop' => 'fill',
            'quality' => 'auto'
        ],
        'lesson_thumbnail' => [
            'width' => 640,
            'height' => 360,
            'crop' => 'fill',
            'quality' => 'auto'
        ],
        'post_thumbnail' => [
            'width' => 500,
            'height' => 300,
            'crop' => 'fill',
            'quality' => 'auto'
        ],
        'readlist_images' => [
            'width' => 800,
            'height' => 450,
            'crop' => 'fill',
            'quality' => 'auto'
        ]
    ]
];