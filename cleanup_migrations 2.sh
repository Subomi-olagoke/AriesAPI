#!/bin/bash

# Keep only migrations for:
# - users, profiles
# - open_libraries, library_urls, library_content, library_follows
# - readlists, readlist_items
# - alex_points tables
# - notifications
# - likes, reports
# - user blocks/mutes
# - password resets, personal access tokens

cd database/migrations

# Remove post/comment migrations
rm -f *posts*.php *comment*.php

# Remove course migrations
rm -f *course*.php *enrollment*.php *lesson*.php *section*.php

# Remove live class migrations
rm -f *live_class*.php *livestream*.php

# Remove messaging migrations
rm -f *conversation*.php *message*.php *mention*.php

# Remove tutoring/hiring migrations
rm -f *hire*.php *tutoring*.php

# Remove subscription/payment migrations
rm -f *subscription*.php *payment*.php *paystack*.php *refund*.php

# Remove channel/collaboration migrations
rm -f *channel*.php *collaborative*.php *content_version*.php *content_permission*.php *content_comment*.php *operation*.php *document*.php

# Remove Cogni migrations
rm -f *cogni*.php *cognition*.php

# Remove educator migrations
rm -f *educator*.php

# Remove other feature migrations
rm -f *follow*.php *bookmark*.php *setup*.php *topic*.php *categories*.php *verification*.php *waitlist*.php
rm -f *feed*.php *search*.php *trending*.php *suggested*.php *available*.php
rm -f *premium*.php *hive*.php

echo "Migrations cleanup complete!"

