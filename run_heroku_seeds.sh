#!/bin/bash
# Script to run seeders on Heroku

# Replace YOUR_APP_NAME with your actual Heroku app name
APP_NAME="YOUR_APP_NAME"  

# Run the AlexPoints seeder
echo "Seeding AlexPoints system..."
heroku run "php artisan alexpoints:seed" --app $APP_NAME

# Run any other seeders you need
# echo "Running other seeders..."
# heroku run "php artisan db:seed --class=OtherSeeder" --app $APP_NAME

echo "Done!"