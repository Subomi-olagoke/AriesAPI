#!/bin/bash

# Services to KEEP:
# - OpenLibraryService, UrlFetchService, ContentModerationService, FileUploadService
# - AlexPointsService
# - LibraryApiService (if needed)

cd app/Services

# Remove course related
rm -f CourseService.php CourseLessonService.php CourseSectionService.php

# Remove Cogni AI
rm -f CogniService.php CognitionService.php EnhancedCogniService.php AIService.php

# Remove payments
rm -f PaystackService.php

# Remove other services
rm -f GoogleMeetService.php YouTubeService.php
rm -f ExaSearchService.php ExaTrendService.php GPTSearchService.php
rm -f PersonalizedFactsService.php PersonalizedLearningPathService.php
rm -f AIReadlistImageService.php AICoverImageService.php
rm -f ApiClientService.php CloudinaryService.php

echo "Services cleanup complete!"

