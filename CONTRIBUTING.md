# Contributing to Multi-Tenant Reporting Platform

Thank you for your interest in contributing to the Multi-Tenant Reporting Platform! This document provides guidelines and instructions for contributing to the project.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Workflow](#development-workflow)
- [Coding Standards](#coding-standards)
- [Commit Messages](#commit-messages)
- [Pull Request Process](#pull-request-process)
- [Testing](#testing)
- [Documentation](#documentation)

## Code of Conduct

### Our Pledge

We are committed to providing a welcoming and inspiring community for all. Please be respectful and constructive in all interactions.

### Our Standards

- **Be Respectful:** Treat everyone with respect and consideration
- **Be Collaborative:** Work together to achieve the best outcome
- **Be Professional:** Keep discussions focused and productive
- **Be Open-Minded:** Welcome different perspectives and ideas

## Getting Started

### Prerequisites

- PHP 8.2+
- Node.js 18+
- Docker and Docker Compose
- Git
- PostgreSQL 16+ (or use Docker)

### Setting Up Development Environment

1. **Fork and Clone the Repository**
   ```bash
   git clone https://gitlab.athenanetworks.com.au/athenanetworks/reporting.git
   cd reporting
   ```

2. **Install Dependencies**
   ```bash
   # Backend
   cd backend
   composer install
   
   # Frontend
   cd ../frontend
   npm install
   ```

3. **Configure Environment**
   ```bash
   # Backend
   cp .env.example .env
   php artisan key:generate
   
   # Frontend
   cp .env.example .env
   ```

4. **Start Development Environment**
   ```bash
   docker-compose up -d
   npm run dev # in frontend directory
   php artisan serve # in backend directory
   ```

## Development Workflow

### Branch Naming Convention

- `feature/` - New features (e.g., `feature/add-kaseya-integration`)
- `bugfix/` - Bug fixes (e.g., `bugfix/customer-card-overflow`)
- `hotfix/` - Critical production fixes
- `refactor/` - Code refactoring
- `docs/` - Documentation updates

### Development Process

1. **Create a Branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make Changes**
   - Write clean, readable code
   - Follow coding standards (see below)
   - Add tests for new functionality
   - Update documentation as needed

3. **Test Your Changes**
   ```bash
   # Backend tests
   cd backend
   php artisan test
   
   # Frontend tests
   cd frontend
   npm run test
   ```

4. **Commit Your Changes**
   ```bash
   git add .
   git commit -m "feat: add Kaseya integration support"
   ```

5. **Push and Create Merge Request**
   ```bash
   git push origin feature/your-feature-name
   ```

## Coding Standards

### Backend (Laravel/PHP)

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards
- Use type hints for all method parameters and return types
- Write PHPDoc blocks for all classes and methods
- Keep methods small and focused (single responsibility)
- Use dependency injection instead of facades where possible

**Example:**
```php
<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Collection;

class CustomerMappingService
{
    /**
     * Auto-match integration customers to unified customers.
     *
     * @param Collection $integrationCustomers
     * @param int $threshold Similarity threshold (0-100)
     * @return array Matched customers with confidence scores
     */
    public function autoMatch(Collection $integrationCustomers, int $threshold = 85): array
    {
        // Implementation
    }
}
```

### Frontend (React/TypeScript)

- Use functional components with hooks
- Define TypeScript interfaces for all props and data structures
- Use meaningful variable and function names
- Keep components small and focused
- Use Tailwind CSS utility classes (avoid custom CSS when possible)
- Follow React best practices (memo, useCallback, useMemo where appropriate)

**Example:**
```typescript
interface CustomerCardProps {
  customer: Customer
  onEdit: (customer: Customer) => void
  onDelete: (id: number) => void
}

export function CustomerCard({ customer, onEdit, onDelete }: CustomerCardProps) {
  const handleEdit = useCallback(() => {
    onEdit(customer)
  }, [customer, onEdit])
  
  return (
    <Card className="overflow-hidden">
      {/* Component content */}
    </Card>
  )
}
```

### Database

- Use migrations for all schema changes
- Include both `up()` and `down()` methods
- Add indexes for frequently queried columns
- Use descriptive table and column names
- Document complex queries with comments

## Commit Messages

We follow the [Conventional Commits](https://www.conventionalcommits.org/) specification:

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Types

- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, missing semicolons, etc.)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks
- `perf`: Performance improvements

### Examples

```
feat(integrations): add Kaseya integration support

Implemented the Kaseya integration following the integration framework contract.
Includes customer sync, historical data import, and preview functionality.

Closes #123
```

```
fix(customers): resolve customer card overflow issue

Customer names can now wrap to a second line to prevent overflow.
Added proper CSS classes for text wrapping and container constraints.
```

## Pull Request Process

### Before Submitting

1. ✅ All tests pass
2. ✅ Code follows coding standards
3. ✅ Documentation updated (if needed)
4. ✅ CHANGELOG.md updated
5. ✅ No merge conflicts with main branch
6. ✅ Commit messages follow convention

### Merge Request Template

When creating a merge request, use this template:

```markdown
## Description
Brief description of the changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
Describe the tests you ran and how to reproduce

## Checklist
- [ ] My code follows the style guidelines
- [ ] I have performed a self-review
- [ ] I have commented my code where necessary
- [ ] I have updated the documentation
- [ ] My changes generate no new warnings
- [ ] I have added tests that prove my fix/feature works
- [ ] New and existing unit tests pass locally
- [ ] I have updated the CHANGELOG.md

## Screenshots (if applicable)
Add screenshots to help explain your changes

## Related Issues
Closes #issue_number
```

### Review Process

1. At least one approval required from maintainers
2. All CI/CD checks must pass
3. No merge conflicts
4. Code review feedback must be addressed

## Testing

### Backend Testing

```bash
cd backend

# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature

# Run with coverage
php artisan test --coverage
```

### Frontend Testing

```bash
cd frontend

# Run all tests
npm run test

# Run in watch mode
npm run test:watch

# Run with coverage
npm run test:coverage
```

### Integration Testing

Test your changes with the full stack running:

```bash
docker-compose up -d
# Access the application at http://localhost:5173
```

## Documentation

### Code Documentation

- Add PHPDoc blocks to all PHP classes and methods
- Add JSDoc comments to complex TypeScript functions
- Document API endpoints in README.md
- Update architecture documentation when adding new patterns

### User Documentation

- Update user guides for new features
- Add screenshots and examples
- Keep language clear and concise
- Consider non-technical users

### API Documentation

- Document all API endpoints
- Include request/response examples
- Document error responses
- Keep OpenAPI/Swagger spec updated

## Questions?

If you have questions about contributing, please:

1. Check existing documentation
2. Search closed issues and MRs
3. Ask in the project's communication channel
4. Create a new issue with the `question` label

## License

By contributing, you agree that your contributions will be licensed under the MIT License.

---

Thank you for contributing to make this project better! 🎉
