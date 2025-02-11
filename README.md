<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Local Setup
1. composer install
   - make sure sodium ext is enabled in php.ini
2. npm i
4. copy env.example -> env
3. php artisan key:generate
4. php artisan migrate
5. php artisan passport:install
   - Would you like to run all pending database migrations? - yes
   - Do not create personal access and password grant clients
6. php artisan passport:client --public
   - Which user ID should the client be assigned to? - press enter
   - What should we name the client? - cross-authentication
   - Where should we redirect the request.. - press enter
7. php artisan serve

## Available API endpoints and Documentation
### User Registration:

```http
POST /api/register
```

This endpoint can be used for creating new users.

Expected Payload:
```json
{
    "name": "Test",
    "email": "Test@testing.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

Success Response:

```json
{
    "message": "User Test successfully created"
}
```

### User Login:

```http
POST /api/login
```

A custom endpoint used for logging in an user. Once request is submitted, and user Email/Password are verified, oauth access and refresh tokens will be generated and associated
with the authenticated user in the database, after which, JWT token will be generated and returned along with refresh token and other parameters in the response.

Expected Payload:
```json
{
    "email": "Test@testing.com",
    "password": "password123",
}
```

Success Response:

```json
{
    "data": {
        "user": {
            "email": "Test@testing.com"
        },
        "token": {
            "access_token": "access_token",
            "token_type": "Bearer",
            "expires_at": "2025-02-26 11:21:58",
            "refresh_token": "refresh_token"
        }
    }
}
```

### TV Code Generation

```http
POST /api/generate-tv-code
```

A custom endpoint for generating 6 digit codes for cross-device authentication. In order to associate generated codes with existing users, user email is required to be passed in the payload.
Once Email is received and validated, a new 6 digit code will be generated and saved in the database. The code along with its parameters such as status gets cached, to avoid opening db connections
every time a poll request is received.

Expected Payload:
```json
{
    "email": "Test@testing.com"
}
```

Success Response:

```json
{
{
    "data": {
        "code": "174401",
        "expires_at": "2025-02-11 12:22:47"
    }
}
}
```

## Poll

```http
POST /api/poll-tv-code
```

Expected Payload:
```json
{
    "code": "174401"
}
```

Endpoint for checking the status of a generated code. If code has been activated using another device, access and refresh tokens will be returned.

Success Response (inactive):
```json
{
    "data": {
        "code": "598925",
        "activated": false,
        "data": {
            "expires_at": "2025-02-11 13:27:34"
        }
    }
}
```

Success Response (active):
```json
{
    "data": {
        "code": "598925",
        "activated": true,
        "data": {
            "expires_at": "2025-02-11 13:27:34",
            "token": {
                "access_token": "access_token_jwt",
                "token_type": "Bearer",
                "expires_at": "2025-02-26 11:21:58",
                "refresh_token": "refresh_token"
            }
        }
    }
}
```

## Code Activation

```http
POST /api/active-tv-code
```

This endpoint can be used for activating a TV code. The endpoint is protected by a middleware which validates the provided bearer token, additionally, scope of the token is also validated, thus
bearer token is also necessary, this token can be obtained by using the Log In endpoint.

Authorization - Bearer Token

Expected Payload:
```json
{
    "code": "598925"
}
```

Success Response:
```json
{
    "message": "TV code activated successfully",
    "data": {
        "activated": true,
        "expires_at": "2025-02-11 13:27:34"
    }
}
```

## Refresh

```http
POST /api/refresh
```

This endpoint can be used for obtaining new access and refresh token. Once refresh token is received, the associated access token will be fetched, and both will get revoked, to make sure previous jwt is no longer valid.

As a response, a new pair of refresh/access tokens will be generated, along with the jwt token, and returned in the response.

Expected Payload:
```json
{
    "refresh_token": "refresh_token"
}
```

Success Response:
```json
{
    "data": {
        "token": {
            "access_token": "access_token_jwt",
            "token_type": "Bearer",
            "expires_at": "2025-02-26 10:39:32",
            "refresh_token": "refresh_token"
        }
    }
}
```