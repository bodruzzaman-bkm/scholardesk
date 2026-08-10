# 📚 ScholarDesk

ScholarDesk is a comprehensive academic paper management system built with Laravel. It provides a structured platform for researchers to upload, organize, and manage their research papers within custom collections, while administrators can oversee the platform's user activities. This project is developed as part of the CSE470 curriculum.

---

## ✨ Key Features

* **Secure Authentication:** Robust login and registration system powered by Laravel Breeze.
* **Role-Based Access Control (RBAC):** Distinct user roles for **Administrators** and **Researchers** to manage permissions and access levels.
* **Paper Management:** Upload, view, and organize academic papers seamlessly.
* **Smart Collections:** Group related papers into customized collections. Features a Many-to-Many database architecture allowing a single paper to exist across multiple collections.
* **Modern UI:** Responsive and fast user interface styled with Tailwind CSS and compiled via Vite.

---

## 🛠️ Tech Stack

* **Backend:** PHP 8.x, Laravel
* **Frontend:** Blade Templates, Tailwind CSS, JavaScript
* **Database:** PostgreSQL
* **Tools:** Composer, Node.js (v20+), NPM, Vite

---

## 📋 Prerequisites

Before you begin, ensure you have the following installed on your local machine (or WSL environment):

* **PHP** (v8.2 or higher)
* **Composer**
* **Node.js** (v20 or v22) and **NPM**
* **PostgreSQL**
* **Git**

---

## 🚀 Getting Started

Follow these steps to set up the project locally for development and testing.

### 1. Clone the Repository
```bash
git clone [https://github.com/bodruzzaman-bkm/scholardesk.git](https://github.com/bodruzzaman-bkm/scholardesk.git)
cd scholardesk

```

### 2. Install Dependencies

Install the required PHP and Node.js packages:

```bash
composer install
npm install

```

### 3. Environment Setup

Create a copy of the `.env.example` file and generate the application key:

```bash
cp .env.example .env
php artisan key:generate

```

### 4. Database Configuration

1. Open your database manager (e.g., DBeaver or pgAdmin) and create a new PostgreSQL database named `scholardesk`.
2. Open the `.env` file in the project root and update the database credentials:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=scholardesk
DB_USERNAME=postgres
DB_PASSWORD=your_postgres_password

```

### 5. Run Migrations

Generate the required database tables (Users, Papers, Collections, etc.):

```bash
php artisan migrate

```

---

## ▶️ Running the Application

To run the application locally, you need to start both the frontend compiler and the backend server. Open **two separate terminal instances** in the project directory:

**Terminal 1: Start the Vite frontend server**

```bash
npm run dev

```

**Terminal 2: Start the Laravel backend server**

```bash
php artisan serve

```

Once both servers are running, open your web browser and navigate to:
**`http://localhost:8000`**

---

## 🤝 Contributing Guidelines

If you are a team member contributing to this project, please follow these steps:

1. **Pull the latest changes** from the `main` branch before starting your work:
```bash
git pull origin main

```


2. **Create a new branch** for your feature or bug fix:
```bash
git checkout -b feature/your-feature-name

```


3. Commit your changes with clear, descriptive messages.
4. Push your branch to the repository and open a **Pull Request (PR)** for review.

---

## 📄 License

This project is open-source and available under the [MIT License](https://www.google.com/search?q=LICENSE).
