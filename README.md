# GregTech New Horizons API (Laravel Backend)

[![Latest Stable Version](https://img.shields.io/packagist/v/laravel/framework)](https://packagist.org/packages/laravel/framework)
[![License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE.md)

<!-- TODO: [![Build Status](https://img.shields.io/github/actions/workflow/status/Dubbie/gtnh-api/your-ci-workflow.yml?branch=main)](https://github.com/Dubbie/gtnh-api/actions) -->

A RESTful API backend built with Laravel to manage crafting recipes, items, calculation requirements, and related entities for the GregTech New Horizons (GTNH) Minecraft modpack. This project serves as the backend data source, intended to be consumed by frontend applications or other services.

## Key Features

-   **RESTful API:** Provides endpoints for managing GTNH data.
-   **CRUD Operations:** Manage Items, Crafting Methods, Recipes, and User Preferred Recipes.
-   **Crafting Calculator:** Endpoint to calculate complex crafting requirements based on target items and quantities, considering user preferences.
-   **Layered Architecture:** Organised structure following best practices:
    -   **Controllers:** Handle HTTP requests and responses.
    -   **Services:** Contain core business logic and orchestrate operations.
    -   **Repositories:** Abstract data access logic (using Eloquent).
    -   **API Resources:** Transform data models into consistent JSON responses.
    -   **Form Requests:** Handle validation and authorization for incoming requests.
-   **API Querying:** Robust filtering, sorting, and pagination powered by [Spatie Query Builder](https://spatie.be/docs/laravel-query-builder/current/introduction).
-   **API Authentication:** Token-based authentication using [Laravel Sanctum](https://laravel.com/docs/sanctum).
-   **API Documentation:** Automatically generated, interactive API documentation powered by [Dedoc/Scramble](https://scramble.dedoc.co/).
-   **Database Management:** Uses Laravel's Eloquent ORM and Migrations for schema management.

## Tech Stack & Tools

-   **Framework:** [PHP 8.1+](https://www.php.net/) / [Laravel 10.x](https://laravel.com/)
-   **Database:** MySQL (Recommended)
-   **API Documentation:** [Dedoc/Scramble](https://scramble.dedoc.co/)
-   **API Querying:** [Spatie Laravel Query Builder](https://spatie.be/docs/laravel-query-builder/current/introduction)
-   **Authentication:** [Laravel Sanctum](https://laravel.com/docs/sanctum)
-   **Development Environment:** [DDEV](https://ddev.readthedocs.io/) (Recommended)
-   **Testing:** [PHPUnit](https://phpunit.de/)
-   **Dependency Management:** [Composer](https://getcomposer.org/)

## Project Structure Overview

The project follows a standard Laravel structure with emphasis on the layered architecture for the API:

-   `app/Http/Controllers/Api/V1/`: API Controllers (versioned)
-   `app/Http/Requests/`: Form Request Validation Classes
-   `app/Http/Resources/`: API Resource Classes (Data Transformation)
-   `app/Services/`: Business Logic Layer
-   `app/Repositories/Contracts/`: Repository Interfaces
-   `app/Repositories/Eloquent/`: Eloquent Repository Implementations
-   `app/Models/`: Eloquent Models & Relationships
-   `app/Exceptions/`: Custom Exception Classes
-   `routes/api.php`: API Route Definitions
-   `config/`: Application Configuration (including `scramble.php`, `query-builder.php`)
-   `database/migrations/`: Database Schema Migrations
-   `database/seeders/`: Database Seeders
-   `tests/`: Feature and Unit Tests

## Getting Started

### Prerequisites

-   [PHP](https://www.php.net/manual/en/install.php) (>= 8.1)
-   [Composer](https://getcomposer.org/)
-   [DDEV](https://ddev.readthedocs.io/en/latest/users/install/ddev-installation/) (Recommended for local development) or a compatible local server environment (e.g., Laragon, Valet, Sail).
-   A MySQL Database Server

### Installation (Using DDEV)

1.  **Clone the repository:**

    ```bash
    git clone https://github.com/Dubbie/gtnh-api.git
    cd gtnh-api
    ```

2.  **Copy Environment File:**

    ```bash
    cp .env.example .env
    ```

3.  **Configure DDEV:**

    -   DDEV automatically configures database connections based on its services. Usually, no changes are needed in `.env` for `DB_*` variables when using DDEV defaults.
    -   Ensure `APP_URL` in `.env` matches the URL DDEV will provide (check `ddev describe`).

4.  **Start DDEV:**

    ```bash
    ddev start
    ```

5.  **Install Composer Dependencies:**

    ```bash
    ddev composer install
    ```

6.  **Generate Application Key:**

    ```bash
    ddev artisan key:generate
    ```

7.  **Run Database Migrations:**
    ```bash
    ddev artisan migrate
    ```

The application should now be running and accessible via the URL provided by `ddev describe`.

## API Documentation

This API uses [Dedoc/Scramble](https://scramble.dedoc.co/) to generate interactive OpenAPI documentation.

-   **Access:** Once the application is running, access the documentation at `/api/documentation` (or the path configured in `config/scramble.php`).
-   **Generation:** Documentation is usually generated automatically based on code analysis. If needed, you can manually regenerate it:
    ```bash
    ddev artisan scramble:generate
    ```

## Running Tests

To run the PHPUnit test suite:

```bash
ddev artisan test
```

## License

This project is open-sourced software licensed under the [MIT License](LICENSE.md).
