# Contributing to Laravel LifeCycle Hooks

We love your input! We want to make contributing to Laravel LifeCycle Hooks as easy and transparent as possible.

## 🚀 Getting Started

### Development Setup
1. Fork the repo
2. Clone your fork: `git clone https://github.com/jeffleyd/lifecycle.git`
3. Install dependencies: `composer install`
4. Run tests: `composer test`

### Development Philosophy: Diffused Programming

This project embodies **Diffused Programming** principles:
- Core logic stays clean and focused
- Features are added via hooks without modifying core
- Different skill levels can contribute meaningfully

## 🎯 How to Contribute

### 🎨 For Junior Developers
**Perfect for learning and contributing:**
- Create new hook examples in `examples/`
- Add test cases for edge scenarios
- Improve documentation with real-world examples
- Fix typos and improve code comments

Example: Create a new hook for order processing
```php
// examples/hooks/OrderNotificationHook.php
class OrderNotificationHook implements LifeCycleHook
{
    // Implementation here
}
```

### 🚀 For Mid-Level Developers  
**Great impact opportunities:**
- Optimize hook discovery performance
- Add new lifecycle patterns
- Improve error handling and logging
- Create additional ServiceProvider features

Example: Add caching to hook discovery
```php
protected function getCachedHooks(string $class): Collection
{
    return Cache::remember("lifecycle.hooks.{$class}", 3600, fn() => 
        $this->discoverHooks($class)
    );
}
```

### 🏗️ For Senior Developers
**Architecture and advanced features:**
- Design new interfaces and contracts
- Add async hook execution support
- Implement distributed lifecycle events
- Create performance monitoring tools

Example: Add async support
```php
public function runHookAsync(string $lifeCycle, array $args): Promise
{
    return Queue::push(new ExecuteHooksJob($lifeCycle, $args));
}
```

## 📝 Contribution Guidelines

### Code Standards
- Follow PSR-12 coding standard
- Use PHP 8.2+ features and type hints
- Write comprehensive tests for new features
- Update documentation for any API changes

### Testing
```bash
# Run all tests
composer test

# Run tests with coverage
composer test:coverage

# Run specific test
./vendor/bin/phpunit tests/Unit/SpecificTest.php
```

### Pull Request Process
1. Create a feature branch: `git checkout -b feature/amazing-feature`
2. Make your changes with tests
3. Ensure all tests pass: `composer test`
4. Update documentation if needed
5. Submit a pull request with clear description

### Commit Messages
Use conventional commits:
- `feat:` new features
- `fix:` bug fixes  
- `docs:` documentation changes
- `test:` test additions/modifications
- `refactor:` code refactoring

Examples:
```
feat: add async hook execution support
fix: resolve hook discovery caching issue
docs: add e-commerce integration example
test: add edge cases for lifecycle validation
```

## 🎯 Ideas for Contributions

### Easy (Junior Level)
- [ ] Add more hook examples (inventory, shipping, notifications)
- [ ] Create integration guides for popular packages
- [ ] Add multilingual README translations
- [ ] Improve error messages with helpful suggestions

### Medium (Mid Level)  
- [ ] Implement hook condition system
- [ ] Add hook priority/ordering support
- [ ] Create hook performance profiler
- [ ] Add configuration validation

### Advanced (Senior Level)
- [ ] Distributed lifecycle events across microservices
- [ ] Hook marketplace/plugin system
- [ ] GraphQL integration for lifecycle monitoring
- [ ] Advanced caching strategies

## 🤝 Community

### Communication
- 🐛 **Bug Reports**: Use GitHub Issues with detailed reproduction steps
- 💡 **Feature Requests**: Open GitHub Discussions for community input
- ❓ **Questions**: Use GitHub Discussions or Stack Overflow
- 💬 **Chat**: Join our Discord community

### Code of Conduct
- Be respectful and inclusive
- Focus on constructive feedback
- Help others learn and grow
- Celebrate diverse contributions

## 🏆 Recognition

Contributors will be:
- Listed in README acknowledgments
- Featured in release notes
- Invited to maintainer discussions
- Given credit in documentation

## 📚 Resources

- [Laravel Documentation](https://laravel.com/docs)
- [PHP The Right Way](https://phptherightway.com/)
- [PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)

---

Thank you for contributing to Laravel LifeCycle Hooks! 🚀