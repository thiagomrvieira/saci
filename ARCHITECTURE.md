# Saci Architecture

## Overview

Saci follows Laravel's best practices and modern PHP patterns, implementing a clean, modular architecture with proper separation of concerns.

## Architecture Components

### Core Classes

#### 1. **SaciServiceProvider**
- **Responsibility**: Package bootstrapping and service registration
- **Features**:
  - Configuration merging
  - View loading
  - Middleware registration
  - Service container bindings
  - Configuration publishing

#### 2. **SaciMiddleware**
- **Responsibility**: Main middleware orchestrator
- **Features**:
  - Request validation
  - View tracking coordination
  - Response modification coordination
- **Dependencies**: TemplateTracker, DebugBarInjector, RequestValidator

#### 3. **TemplateTracker**
- **Responsibility**: View tracking and data collection
- **Features**:
  - View creator registration
  - Template path extraction
  - Data filtering and sanitization
  - Collection management

#### 4. **DebugBarInjector**
- **Responsibility**: Response modification and debug bar rendering
- **Features**:
  - HTML content injection
  - View rendering
  - Error handling
- **Dependencies**: TemplateTracker

#### 5. **RequestValidator**
- **Responsibility**: Request validation logic
- **Features**:
  - Environment validation
  - Request type validation
  - Configuration-based validation

#### 6. **SaciConfig**
- **Responsibility**: Configuration management
- **Features**:
  - Centralized configuration access
  - Type-safe configuration methods
  - Default value management

#### 7. **SaciInfo**
- **Responsibility**: Package metadata
- **Features**:
  - Version information
  - Author information
  - Package constants

## Design Patterns

### 1. **Dependency Injection**
- All dependencies are injected through constructors
- Services are registered as singletons in the service container
- Follows Laravel's IoC container patterns

### 2. **Single Responsibility Principle**
- Each class has a single, well-defined responsibility
- Clear separation between tracking, validation, and injection logic

### 3. **Configuration Pattern**
- Centralized configuration management
- Type-safe configuration access
- Environment-based configuration

### 4. **Service Container Pattern**
- Proper Laravel service registration
- Singleton pattern for shared resources
- Automatic dependency resolution

## Data Flow

```
Request → Middleware → RequestValidator → TemplateTracker → DebugBarInjector → Response
```

1. **Request** enters the middleware
2. **RequestValidator** determines if tracing should occur
3. **TemplateTracker** registers view creators and collects data
4. **DebugBarInjector** modifies the response with debug information
5. **Response** is returned with debug bar injected

## Configuration Structure

```php
'saci' => [
    'enabled' => true,
    'auto_register_middleware' => true,
    'environments' => ['local', 'development'],
    'hide_data_fields' => ['password', 'token', 'secret'],
    'ui' => [
        'position' => 'bottom',
        'theme' => 'dark',
        'max_height' => '30vh'
    ]
]
```

## Error Handling

- Graceful error handling in all components
- Logging for debugging purposes
- Fallback mechanisms for failed operations
- Non-intrusive error recovery

## Performance Considerations

- Lazy loading of components
- Efficient data collection using Laravel Collections
- Minimal impact on application performance
- Configurable activation based on environment

## Testing Strategy

- Unit tests for each component
- Integration tests for middleware
- Configuration testing
- Error scenario testing

## Future Enhancements

- Event-driven architecture for extensibility
- Plugin system for custom data collectors
- Advanced filtering and search capabilities
- Performance metrics collection