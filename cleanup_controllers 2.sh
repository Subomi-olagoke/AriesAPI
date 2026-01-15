#!/bin/bash

# Controllers to KEEP (core features only)
# - Libraries: OpenLibraryController, AdminApiLibraryController, LibraryFollowController
# - Readlists: ReadlistController
# - Points: AlexPointsController, AlexPointsPaymentController
# - Profiles: ProfileController
# - Notifications: NotificationController
# - Auth: AuthManager, ForgotPasswordManager, ResetPasswordController, GoogleController
# - Onboarding: OnboardingController
# - Like/Report: LikeController, ReportController
# - Device: DeviceController

# Controllers to DELETE
cd app/Http/Controllers

# Remove post/feed related
rm -f PostController.php FeedController.php CommentController.php

# Remove course related
rm -f CoursesController.php CourseLessonController.php CourseSectionController.php CourseRatingController.php EnrollmentController.php

# Remove live class related
rm -f LiveClassController.php LiveClassChatController.php livestreamController.php

# Remove messaging
rm -f MessageController.php

# Remove tutoring/hiring
rm -f HireController.php HireRequestController.php HireSessionController.php HireSessionDocumentController.php HireSessionVideoController.php

# Remove subscriptions/payments
rm -f SubscriptionController.php PaystackController.php PaymentController.php PaymentMethodController.php PaymentSplitController.php PremiumController.php PremiumStorefrontController.php

# Remove channels/collaboration
rm -f ChannelController.php CollaborationController.php DocumentController.php

# Remove Cogni AI (for v2)
rm -f CogniController.php CogniController.php.* EnhancedCogniController.php CognitionController.php PostAnalysisController.php

# Remove educators
rm -f EducatorsController.php EducatorProfileController.php EducatorCoursesController.php EducatorDashboardController.php EducatorEarningsController.php EducatorAuthController.php

# Remove other features
rm -f BookmarkController.php FollowController.php SearchController.php SetupController.php TrendingTopicsController.php TopicsController.php CategoriesController.php SuggestedController.php AvailableController.php

# Remove admin (except library admin)
rm -f AdminController.php AdminUserController.php AdminDashboardStatsController.php AdminLibraryController.php AdminAuthController.php

# Remove other
rm -f FileController.php MentionController.php MailController.php WaitlistController.php VerificationController.php BlockController.php

# Remove Hive
rm -rf Hive/

echo "Cleanup complete!"

