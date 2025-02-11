<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Local Setup
1. composer install
 -- make sure sodium ext is enabled in php.ini
2. npm i
4. copy env.example -> env
3. php artisan key:generate
4. php artisan passport:install
   - Do not run database migrations
   - Do not create personal access and password grant clients
5. php artisan passport:client --public
   - Which user ID should the client be assigned to? - press enter
   - What should we name the client? - cross-authentication
   - Where should we redirect the request.. - press enter
6. php artisan migrate
7. php artisan serve

## API Documentation
API documentation goes here...