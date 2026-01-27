# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-01-23

### Added
- Initial release of Laravel Logger package
- Typed logging with dedicated LogObject classes (GeneralLogObject, ApiLogObject, JobLogObject, IntegrationLogObject, OrmLogObject, ErrorLogObject)
- OpenSearch integration with dynamic index routing
- Kafka support via REST Proxy
- Index file channel for JSONL file logging
- Deferred logging with auto-flush mechanism
- Multiple log indices: api_log, general_log, job_log, integration_log, orm_log, error_log
- Automatic request_id propagation
- Automatic error logging with fatal error support
- JSON pretty printing for request/response bodies and headers
- ORM logging with query tracking and model events
- File locking for race condition prevention in multi-worker environments
- Comprehensive test suite with 99.95% code coverage
- Automatic installation command
- Verification command for installation checks
- OpenSearch setup scripts with Dashboards integration
- Docker Compose configurations for OpenSearch and Kafka
- Complete documentation with examples and guides

### Security
- Secure file permissions (0755 instead of 0777)
- Content-Length checks to prevent DoS with large request bodies
- Memory limits for deferred logging queue

### Performance
- Batch processing for OpenSearch and Kafka
- Exponential backoff retry mechanism
- File locking to prevent race conditions in pruning operations
- Memory leak prevention with transaction ID cleanup

### Documentation
- Comprehensive README with Quick Start guide
- Installation guide
- Usage examples
- Migration guide
- OpenSearch setup guide
- Architecture and design documentation
