# Alexandria Admin Dashboard

This is the administration dashboard for the Alexandria platform, providing tools for managing users, content, libraries, and more.

## Heroku API Connection

The admin dashboard can connect to the Alexandria API hosted on Heroku for synchronized operations. This allows changes made in the admin dashboard to be reflected in the main application.

### Setup

1. Create a `.env` file in the root directory if it doesn't exist
2. Add the following variables to the `.env` file:

```
HEROKU_API_URL=https://ariesmvp-9903a26b3095.herokuapp.com/api
HEROKU_ADMIN_API_TOKEN=your_admin_api_token
```

3. Replace `your_admin_api_token` with a valid admin API token from the Heroku application

### Features

The admin dashboard supports the following remote operations:

- Creating new libraries
- Approving and rejecting libraries
- Adding content to libraries
- Removing content from libraries
- Generating AI cover images for libraries

If the connection to the API fails, the dashboard will fall back to local operations and display a warning message.

## Development

To start the development server:

```
php artisan serve
```

## Admin Features

The admin dashboard provides tools for:

1. User Management - View, edit, and moderate user accounts
2. Library Management - Create, approve, and manage content libraries
3. Content Moderation - Review and moderate user-generated content
4. Analytics - View platform usage statistics

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).# Updated at Sun Jun 29 23:43:42 WAT 2025
