# Contributing to GPT Chatbot Boilerplate

Thank you for your interest in contributing to the GPT Chatbot Boilerplate! We welcome contributions from the community.

## How to Contribute

### Reporting Issues

If you find a bug or have a feature request:

1. Check if the issue already exists in [GitHub Issues](https://github.com/suporterfid/gpt-chatbot-boilerplate/issues)
2. If not, create a new issue with:
   - Clear title and description
   - Steps to reproduce (for bugs)
   - Expected vs actual behavior
   - Environment details (PHP version, OS, etc.)

### Submitting Changes

1. **Fork the Repository**
   ```bash
   git clone https://github.com/YOUR_USERNAME/gpt-chatbot-boilerplate.git
   cd gpt-chatbot-boilerplate
   ```

2. **Create a Feature Branch**
   ```bash
   git checkout -b feature/your-feature-name
   # or
   git checkout -b bugfix/issue-description
   ```

3. **Set Up Development Environment**
   ```bash
   # Install dependencies
   composer install --dev
   npm install
   
   # Copy environment file
   cp .env.example .env
   
   # Edit .env with your settings
   nano .env
   
   # Start development server
   docker-compose up -d
   ```

4. **Make Your Changes**
   - Write clear, documented code
   - Follow existing code style and conventions
   - Add tests for new features
   - Update documentation as needed

5. **Test Your Changes**
   ```bash
   # Run all tests
   php tests/run_tests.php
   
   # Run static analysis
   composer run analyze
   
   # Run linting
   npm run lint
   
   # Run smoke tests
   bash scripts/smoke_test.sh
   ```

6. **Commit Your Changes**
   ```bash
   git add .
   git commit -m "Clear description of changes"
   ```
   
   Use clear commit messages:
   - `feat: Add new feature X`
   - `fix: Resolve issue with Y`
   - `docs: Update documentation for Z`
   - `test: Add tests for feature X`
   - `refactor: Improve code structure`

7. **Push to Your Fork**
   ```bash
   git push origin feature/your-feature-name
   ```

8. **Open a Pull Request**
   - Go to the original repository
   - Click "New Pull Request"
   - Select your branch
   - Fill in the PR template with:
     - Description of changes
     - Related issue numbers
     - Testing performed
     - Screenshots (if UI changes)

## Development Guidelines

### Code Style

- **PHP**: Follow PSR-12 coding standards
- **JavaScript**: Follow ESLint configuration
- **Comments**: Write clear, helpful comments for complex logic
- **Naming**: Use descriptive variable and function names

### Testing Requirements

All contributions must include appropriate tests:

- **Unit Tests**: For new functions and classes
- **Integration Tests**: For API endpoints and workflows
- **Documentation**: Update relevant docs for new features

### Pull Request Checklist

Before submitting your PR, ensure:

- [ ] All tests pass
- [ ] Static analysis passes (PHPStan)
- [ ] Linting passes (ESLint)
- [ ] Code is documented
- [ ] Documentation is updated
- [ ] Commit messages are clear
- [ ] PR description is complete

## Code Review Process

1. Maintainers will review your PR
2. Address any requested changes
3. Once approved, your PR will be merged
4. Your contribution will be included in the next release

## Community Guidelines

- Be respectful and constructive
- Help others in discussions
- Follow the [Code of Conduct](CODE_OF_CONDUCT.md)
- Ask questions if you're unsure

## Getting Help

- **Documentation**: Check [docs/](docs/) directory
- **Discussions**: Use [GitHub Discussions](https://github.com/suporterfid/gpt-chatbot-boilerplate/discussions)
- **Issues**: Search existing issues or create a new one

## Types of Contributions Welcome

- 🐛 Bug fixes
- ✨ New features
- 📝 Documentation improvements
- 🧪 Additional tests
- 🎨 UI/UX improvements
- 🌐 Translations
- 📊 Performance improvements
- 🔐 Security enhancements

## Recognition

Contributors will be:
- Listed in release notes
- Credited in the repository
- Part of our growing community

Thank you for contributing to make this project better! 🎉
