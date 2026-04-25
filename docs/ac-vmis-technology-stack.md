# AC-VMIS Technology Stack Documentation

## Introduction

The Asian College Varsity Management Information System (AC-VMIS) was developed as a web-based information system that integrates backend processing, frontend user interaction, database management, build automation, testing, and deployment support. Based on the current project configuration and source files, the system uses a modern full-stack architecture centered on Laravel and Vue.

This document identifies the technologies used in the development of AC-VMIS and explains the role and purpose of each programming language, framework, library, application, and support tool. The discussion is structured for inclusion in thesis documentation.

## Primary Development Technologies

| Category | Technology | Version / Basis | Role and Purpose in AC-VMIS |
| --- | --- | --- | --- |
| Programming Language | PHP | `^8.2` in project requirements | PHP serves as the primary server-side programming language of AC-VMIS. It is used to implement business logic, request handling, authentication, validation, database interaction, notification workflows, and module-level processes such as academic eligibility evaluation and OCR-related backend services. |
| Backend Framework | Laravel | `^12.0` | Laravel functions as the main application framework of the system. It provides the architectural foundation for routing, controllers, models, middleware, migrations, file storage, queues, sessions, validation, and security features. It enables organized and maintainable development of the AC-VMIS backend. |
| Frontend Programming Language | TypeScript | `^5.2.2` | TypeScript is used in the frontend layer to provide typed JavaScript development. Its purpose is to improve code reliability, maintainability, and developer productivity by reducing common runtime errors and clarifying component and data structures. |
| Frontend Framework | Vue.js | `^3.5.13` | Vue.js is the principal frontend framework used to build interactive user interfaces for administrators, coaches, student-athletes, and public users. It supports component-based interface development and responsive page behavior across the system. |
| Full-Stack Bridge | Inertia.js | `@inertiajs/vue3` and `inertiajs/inertia-laravel` | Inertia.js connects the Laravel backend and Vue frontend without requiring a separate API-first architecture for standard page delivery. Its role is to allow Laravel controllers to render Vue pages directly, thereby simplifying full-stack development and preserving a single cohesive application structure. |
| Database Management System | MySQL | configured as default database connection | MySQL serves as the primary relational database management system of AC-VMIS. It stores user accounts, team data, schedules, attendance records, wellness logs, academic documents, eligibility evaluations, and other structured operational records of the system. |
| Build Tool | Vite | `^7.0.4` | Vite is used to compile and bundle frontend assets efficiently during development and production. It provides a fast development server, optimized builds, and support for Vue, TypeScript, and Tailwind CSS integration. |
| Styling Framework | Tailwind CSS | `^4.1.1` | Tailwind CSS is used to design and style the AC-VMIS user interface. Its utility-first approach supports rapid, consistent, and maintainable styling across system pages and components. |
| User Interface Component Library | PrimeVue | `^4.5.4` | PrimeVue provides reusable user interface components such as notifications, form elements, and interface widgets. Its purpose in AC-VMIS is to accelerate interface development and improve visual consistency and usability. |
| Icon Library | PrimeIcons | `^7.0.0` | PrimeIcons supplies icon assets used in the system interface. These icons improve navigation clarity, visual communication, and user experience. |

## Supporting Frontend Libraries

| Technology | Version / Basis | Role and Purpose in AC-VMIS |
| --- | --- | --- |
| Prime UI Themes | `@primeuix/themes` `^2.0.3` | Provides theming support for PrimeVue components. In AC-VMIS, it helps maintain a unified visual presentation of interface elements. |
| VueUse | `@vueuse/core` `^12.8.2` | Supplies reusable Vue composition utilities. It supports cleaner implementation of reactive frontend behavior and shared client-side logic. |
| QRCode | `qrcode` `^1.5.4` | Used to generate QR codes within attendance-related features. Its purpose is to support QR-based verification and schedule attendance workflows in the system. |
| Vue Cal | `vue-cal` `^5.0.1-rc.34` | Provides calendar-based scheduling views for modules involving team activities and operations scheduling. It improves the presentation and management of time-based records. |
| Class Variance Authority | `class-variance-authority` `^0.7.1` | Helps organize variant-based CSS class generation for reusable UI patterns. |
| CLSX | `clsx` `^2.1.1` | Simplifies conditional class composition in Vue components, improving readability of interface code. |
| Tailwind Merge | `tailwind-merge` `^3.2.0` | Resolves conflicting Tailwind utility classes and supports cleaner styling logic. |
| Laravel Wayfinder | `laravel/wayfinder` and `@laravel/vite-plugin-wayfinder` | Supports typed route and form generation between backend and frontend layers. Its purpose is to improve consistency and reduce routing errors in the application. |

## Backend Support and Application Services

| Technology | Version / Basis | Role and Purpose in AC-VMIS |
| --- | --- | --- |
| Laravel Queue | configured with `database` queue connection | Handles background job processing within the system. In AC-VMIS, queue support is important for deferred or asynchronous tasks such as OCR processing and notification delivery workflows. |
| Laravel Session Management | configured with `database` session driver | Stores and manages authenticated user sessions in the database, supporting secure access control and persistent login state. |
| Laravel Cache | configured with `database` cache store | Provides application caching through database-backed storage, improving efficiency for selected runtime operations. |
| Laravel File Storage | local disk with optional S3 configuration | Manages uploaded files such as academic documents, attachments, and generated assets. The configuration also allows future or alternative cloud-based storage through Amazon S3-compatible settings. |
| Laravel Server-Side Rendering | configured through Inertia SSR entry files | Supports server-side rendering for selected frontend delivery scenarios, improving application responsiveness and rendering flexibility. |
| Laravel Tinker | `^2.10.1` | Provides an interactive environment for backend inspection and development support. It is useful for debugging, testing models, and validating application behavior during development. |
| Laravel Pail | `^1.2.2` | Supports log monitoring during development, helping developers observe runtime behavior and troubleshoot issues efficiently. |

## Development, Quality Assurance, and Code Maintenance Tools

| Category | Technology | Version / Basis | Role and Purpose in AC-VMIS |
| --- | --- | --- | --- |
| Dependency Management | Composer | used through `composer.json` | Composer manages PHP and Laravel dependencies required by the backend of AC-VMIS. It ensures consistent installation of server-side packages and development tools. |
| Dependency Management | npm | used through `package.json` | npm manages JavaScript and frontend dependencies, including Vue, Vite, Tailwind CSS, PrimeVue, and related libraries. |
| Runtime Environment | Node.js | required by Vite/npm workflow; Docker build uses Node.js 20 | Node.js provides the runtime needed for frontend package management, asset compilation, and development tooling execution. |
| Code Formatting | Laravel Pint | `^1.24` | Laravel Pint is used to enforce consistent coding standards in the PHP codebase. It improves maintainability and readability of backend source files. |
| Linting | ESLint | `^9.39.4` | ESLint is used to analyze frontend code for potential problems, style violations, and maintainability concerns in JavaScript and TypeScript files. |
| Code Formatting | Prettier | `^3.4.2` | Prettier formats frontend source files to maintain a consistent code style throughout the project. |
| Import and Tailwind Formatting Plugins | `prettier-plugin-organize-imports`, `prettier-plugin-tailwindcss` | configured in frontend tooling | These plugins improve the organization of imports and the ordering of Tailwind CSS classes, supporting a cleaner and more standardized codebase. |
| Testing Framework | Pest | `^4.3` | Pest is the primary automated testing framework used for AC-VMIS. It supports concise and readable backend and feature tests, improving software reliability. |
| Test Runner Foundation | PHPUnit | configured through `phpunit.xml` | PHPUnit provides the underlying test execution infrastructure for PHP-based automated tests. |
| Test Doubles | Mockery | `^1.6` | Mockery is used for mocking dependencies in tests, enabling isolated verification of system behavior. |
| Test Data Generation | FakerPHP | `^1.23` | FakerPHP generates sample or randomized data for testing and development scenarios. |
| Error Reporting During Development | Collision | `^8.6` | Collision improves the readability of error messages in the command-line development environment, assisting debugging and developer productivity. |

## Deployment and Environment Technologies

| Technology | Version / Basis | Role and Purpose in AC-VMIS |
| --- | --- | --- |
| Docker | project contains `Dockerfile` and `docker/` startup scripts | Docker provides container-based packaging and deployment support for AC-VMIS. It helps standardize runtime environments and simplifies deployment setup. |
| Apache HTTP Server | `php:8.4-apache` deployment image | Apache is used as the web server in the containerized deployment configuration. Its purpose is to serve the Laravel application and public assets over HTTP. |
| XAMPP | current local workspace environment | XAMPP provides a local integrated development environment combining Apache, PHP, and MySQL services. In the present development setup, it supports local execution and testing of AC-VMIS. |
| PHP CLI Development Server | `php artisan serve` | Laravel's built-in development server is also configured for running the system during active development. |
| Concurrent Process Runner | `concurrently` `^9.0.1` | Used to run several development processes together, such as the Laravel server, queue listener, log monitor, and Vite development server. |

## External Service Integrations and Configured Infrastructure

| Technology / Service | Version / Basis | Role and Purpose in AC-VMIS |
| --- | --- | --- |
| Brevo Transactional Email API | configured through mail and services settings | Brevo is used as the transactional email provider for sending system-generated emails such as notifications and announcements. |
| Redis | optional configuration present | Redis is configured as an available support technology for cache, queue, and session-related infrastructure if needed in expanded deployments. |
| Amazon S3-Compatible Storage | optional configuration present | S3-compatible storage is available as an alternative file storage backend for uploaded files and digital assets in scalable deployments. |
| SQLite | used in automated testing configuration | SQLite is used as an in-memory test database during automated test execution. Its purpose is to provide fast and isolated database testing without affecting the main MySQL dataset. |

## Summary

The development of AC-VMIS uses a modern web application stack composed primarily of PHP, Laravel, Vue.js, TypeScript, Inertia.js, MySQL, Vite, Tailwind CSS, and PrimeVue. These technologies collectively support the system's backend processing, frontend interaction, database management, interface design, and full-stack integration.

In addition, AC-VMIS employs a set of supporting tools for quality assurance, code maintenance, deployment, and external service integration, including Pest, PHPUnit, ESLint, Prettier, Docker, Apache, Brevo, and database-backed Laravel services. Together, these technologies provide a structured, maintainable, and scalable foundation for the varsity management information system.

## Basis of Identification

The technologies listed in this document were identified from the current AC-VMIS project files, particularly `composer.json`, `package.json`, `vite.config.ts`, `phpunit.xml`, `.env.example`, the Docker configuration, and the application source files under `app/`, `config/`, and `resources/js/`.
