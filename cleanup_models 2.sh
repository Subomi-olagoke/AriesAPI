#!/bin/bash

# Models to KEEP:
# - User, Profile
# - OpenLibrary, LibraryUrl, LibraryContent
# - Readlist, ReadlistItem, ReadlistLink
# - AlexPointsLevel, AlexPointsRule, AlexPointsTransaction
# - Notification
# - Like, Report
# - UserBlock, UserMute

cd app/Models

# Remove post/comment related
rm -f Post.php PostMedia.php Comment.php

# Remove course related
rm -f Course.php CourseEnrollment.php CourseLesson.php CourseSection.php CourseRating.php LessonProgress.php

# Remove live class related
rm -f LiveClass.php LiveClassChat.php LiveClassMessage.php LiveClassParticipant.php

# Remove messaging
rm -f Message.php Message.php.save Conversation.php Mention.php

# Remove tutoring/hiring
rm -f HireRequest.php HireSession.php HireSessionDocument.php HireSessionParticipant.php HireInstructor.php TutoringSession.php

# Remove subscriptions/payments
rm -f Subscription.php SubscriptionPlan.php Payment.php PaymentMethod.php PaymentSplit.php PaymentLog.php

# Remove channels/collaboration
rm -f Channel.php ChannelMember.php ChannelMessage.php
rm -f CollaborativeContent.php CollaborativeReadlist.php CollaborativeSpace.php ContentComment.php ContentPermission.php ContentVersion.php Operation.php

# Remove Cogni AI
rm -f CogniChat.php CogniChatMessage.php CogniConversation.php

# Remove educators
rm -f Educators.php EducatorRating.php

# Remove other features
rm -f Follow.php Bookmark.php Setup.php Topic.php Categories.php VerificationRequest.php Waitlist.php WaitlistEmail.php

# Remove Hive
rm -rf Hive/

echo "Models cleanup complete!"

