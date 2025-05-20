# Pure PHP E-commerce REST API

A foundational e-commerce REST API built entirely with pure PHP and MySQL, without the use of a full-fledged framework. This project demonstrates a structured approach to building scalable APIs in PHP, including custom routing, database interaction with PDO, and the Repository pattern.

## Features

- **Custom Router:** A custom-built routing system to map API endpoints to controller actions, supporting dynamic URL segments (e.g., `/products/{id}`).
- **Database Interaction:** Uses PHP Data Objects (PDO) for secure and efficient communication with a MySQL database.
- **Repository Pattern:** Implements a repository layer to abstract database operations, keeping controllers clean and focused on business logic.
- **Product CRUD Operations:** Provides full Create, Read, Update, and Delete (CRUD) functionality for product resources.
- **CORS Support:** Configured to handle Cross-Origin Resource Sharing (CORS) for seamless integration with frontend applications (like React).
- **Composer Integration:** Uses Composer for autoloading and defining convenient development scripts.

## Technologies Used

- **PHP** (>= 8.1)
- **MySQL**
- **Composer** (for dependency management and autoloading)

## Prerequisites

Before running this API, ensure you have the following installed and configured:

- **PHP (version 8.1 or higher):**
  - On macOS: `brew install php`
  - On Windows: Use XAMPP or Laragon (recommended, as they include PHP, Apache, and MySQL).
  - On Linux: `sudo apt install php php-cli php-mbstring php-xml php-mysql`
- **Composer:** PHP's dependency manager.
  - Installation guide: [https://getcomposer.org/download/](https://getcomposer.org/download/)
- **MySQL Database Server:**
  - Included with XAMPP/Laragon, or install separately.
- **A MySQL Client:** (e.g., phpMyAdmin, MySQL Workbench, or the MySQL command-line client) to create the database.

## Installation

1.  **Clone the repository:**

    ```bash
    git clone <repository-url> ecommerce-api-pure-php
    cd ecommerce-api-pure-php
    ```

2.  **Install Composer Dependencies:**
    This command will generate the `vendor/autoload.php` file, which is crucial for class autoloading.
    ```bash
    composer install
    ```

## Configuration

1.  **Database Setup:**
    Open `config/bootstrap.php` and configure your MySQL database connection details:

    ```php
    // Database Configuration
    define('DB_HOST', '127.0.0.1');
    define('DB_NAME', 'ecommerce_db'); // Make sure this database exists
    define('DB_USER', 'root');         // Your MySQL username
    define('DB_PASS', '');             // Your MySQL password (often empty for root on local)
    ```

    **Important:** Replace `DB_USER` and `DB_PASS` with your actual MySQL credentials.

2.  **Create the Database:**
    You need to manually create a database named `ecommerce_db` on your MySQL server.

    - **Using phpMyAdmin (recommended for XAMPP users):**

      1.  Start XAMPP Apache and MySQL.
      2.  Go to `http://localhost/phpmyadmin` in your browser.
      3.  Click the "Databases" tab.
      4.  Under "Create database," enter `ecommerce_db` and click "Create."

    - **Using MySQL Command-Line Client:**
      ```bash
      mysql -u root -p # Enter your MySQL password
      CREATE DATABASE ecommerce_db;
      exit;
      ```

3.  **Run Database Migrations:**
    This script will create the `products` table in your `ecommerce_db` database.
    ```bash
    composer migrate
    ```
    You can examine the SQL for table creation in `db_migrate.php`.

## Running the API

You can start the PHP development server using a convenient Composer script:

```bash
composer dev
```

## API Endpoints

The API currently supports CRUD operations for `products`. All responses are in JSON format.

**Base URL:** `http://127.0.0.1:8000/api/v1`

---

### 1. Get All Products

- **URL:** `/api/v1/products`
- **Method:** `GET`
- **Description:** Retrieves a list of all products.
- **Success Response (200 OK):**
  ```json
  {
    "status": "success",
    "message": "Product list retrieved successfully",
    "data": [
      {
        "id": 1,
        "name": "Smartphone",
        "price": "799.00",
        "created_at": "2025-05-21 00:00:00",
        "updated_at": "2025-05-21 00:00:00"
      }
      // ... more products
    ]
  }
  ```
- **Error Response (500 Internal Server Error):**
  ```json
  {
    "status": "error",
    "message": "Failed to retrieve products: <error_details>"
  }
  ```

### 2. Get Single Product by ID

- **URL:** `/api/v1/products/{id}`
- **Method:** `GET`
- **Description:** Retrieves details of a single product by its unique ID.
- **Success Response (200 OK):**
  ```json
  {
    "status": "success",
    "message": "Product retrieved successfully",
    "data": {
      "id": 1,
      "name": "Smartphone",
      "price": "799.00",
      "created_at": "2025-05-21 00:00:00",
      "updated_at": "2025-05-21 00:00:00"
    }
  }
  ```
- **Error Response (404 Not Found):**
  ```json
  {
    "status": "error",
    "message": "Product not found."
  }
  ```
- **Error Response (500 Internal Server Error):**
  ```json
  {
    "status": "error",
    "message": "Failed to retrieve product: <error_details>"
  }
  ```

### 3. Create New Product

- **URL:** `/api/v1/products`
- **Method:** `POST`
- **Description:** Creates a new product.
- **Request Body (JSON):**
  ```json
  {
    "name": "Wireless Earbuds",
    "description": "Noise-cancelling, long battery life.",
    "price": 129.99
  }
  ```
- **Success Response (201 Created):**
  ```json
  {
    "status": "success",
    "message": "Product created successfully",
    "id": 2, // The ID of the newly created product
    "data": {
      "name": "Wireless Earbuds",
      "price": 129.99
    }
  }
  ```
- **Error Response (400 Bad Request):**
  ```json
  {
    "status": "error",
    "message": "Invalid input data. Name and price are required."
  }
  ```
- **Error Response (500 Internal Server Error):**
  ```json
  {
    "status": "error",
    "message": "Failed to create product: <error_details>"
  }
  ```

### 4. Update Existing Product

- **URL:** `/api/v1/products/{id}`
- **Method:** `PUT`
- **Description:** Updates an existing product by its ID.
- **Request Body (JSON):**
  ```json
  {
    "name": "Premium Wireless Earbuds",
    "price": 149.99
    // You can include other fields like description, stock_quantity, image_url
  }
  ```
- **Success Response (200 OK):**
  ```json
  {
    "status": "success",
    "message": "Product updated successfully",
    "data": {
      "id": 2,
      "name": "Premium Wireless Earbuds",
      "price": "149.99",
    ", // Original image_url merged
      "created_at": "2025-05-21 00:00:00",
      "updated_at": "2025-05-21 00:00:00" // Updated timestamp
    }
  }
  ```
- **Error Response (400 Bad Request):**
  ```json
  {
    "status": "error",
    "message": "Invalid input data. Name and price are required for update."
  }
  ```
- **Error Response (404 Not Found):**
  ```json
  {
    "status": "error",
    "message": "Product not found for update."
  }
  ```
- **Error Response (500 Internal Server Error):**
  ```json
  {
    "status": "error",
    "message": "Failed to update product: <error_details>"
  }
  ```

### 5. Delete Product

- **URL:** `/api/v1/products/{id}`
- **Method:** `DELETE`
- **Description:** Deletes a product by its ID.
- **Success Response (204 No Content):**
  - No response body is returned for a 204 status.
- **Error Response (404 Not Found):**
  ```json
  {
    "status": "error",
    "message": "Product not found for deletion."
  }
  ```
- **Error Response (500 Internal Server Error):**
  ```json
  {
    "status": "error",
    "message": "Failed to delete product: <error_details>"
  }
  ```
