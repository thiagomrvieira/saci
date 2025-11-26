# 📊 Code Coverage - Saci

Este documento explica como gerar e visualizar relatórios de cobertura de código para o projeto Saci.

## 🎯 Pré-requisitos

- **Xdebug** ou **PCOV** instalado
- PHP 8.0+

Verificar se Xdebug está instalado:
```bash
php -m | grep xdebug
```

## 🚀 Comandos Rápidos

### Via Composer (Recomendado)

```bash
# Rodar todos os testes
composer test

# Rodar apenas testes unitários
composer test:unit

# Rodar apenas testes de feature
composer test:feature

# Gerar coverage com threshold mínimo de 80%
composer test:coverage

# Gerar coverage HTML (sem threshold)
composer test:coverage-html
```

### Via Script Shell

```bash
# Gerar relatório HTML
./coverage.sh html

# Gerar relatório em texto
./coverage.sh text

# Gerar todos os relatórios
./coverage.sh all
```

### Via Pest Diretamente

```bash
# Com relatório HTML
XDEBUG_MODE=coverage ./vendor/bin/pest --coverage --coverage-html=coverage/html

# Com relatório em texto no terminal
XDEBUG_MODE=coverage ./vendor/bin/pest --coverage

# Com threshold mínimo (ex: 80%)
XDEBUG_MODE=coverage ./vendor/bin/pest --coverage --min=80

# Apenas um teste específico
XDEBUG_MODE=coverage ./vendor/bin/pest tests/Unit/Collectors/DatabaseCollectorTest.php --coverage
```

## 📁 Arquivos Gerados

Após executar os comandos acima, os seguintes arquivos serão gerados no diretório `coverage/`:

```
coverage/
├── html/              # Relatório HTML interativo (abra index.html)
│   ├── index.html
│   ├── dashboard.html
│   └── ...
├── clover.xml         # Formato Clover (CI/CD, SonarQube)
├── coverage.txt       # Relatório em texto puro
└── junit.xml          # Formato JUnit (CI/CD)
```

### 🌐 Visualizar Relatório HTML

```bash
# macOS
open coverage/html/index.html

# Linux
xdg-open coverage/html/index.html

# Windows
start coverage/html/index.html
```

## 📈 Interpretar os Resultados

### Métricas Principais

- **Line Coverage**: % de linhas executadas pelos testes
- **Function Coverage**: % de funções/métodos testados
- **Class Coverage**: % de classes com pelo menos um teste
- **Branch Coverage**: % de branches (if/else) cobertos

### Cores no Relatório HTML

- 🟢 **Verde**: Alta cobertura (>= 80%)
- 🟡 **Amarelo**: Cobertura média (50-80%)
- 🔴 **Vermelho**: Baixa cobertura (< 50%)

## 🎯 Metas de Cobertura

| Componente | Meta | Status |
|------------|------|--------|
| Collectors | 90%+ | 🎯 |
| Support    | 85%+ | ✅ |
| Middleware | 80%+ | 🚧 |
| Overall    | 80%+ | 🚧 |

## 🔧 Configuração

A configuração de coverage está em `phpunit.xml`:

```xml
<coverage>
    <report>
        <html outputDirectory="coverage/html"/>
        <text outputFile="coverage/coverage.txt"/>
        <clover outputFile="coverage/clover.xml"/>
    </report>
</coverage>
```

## 🚫 Excluir Arquivos da Cobertura

No `phpunit.xml`, adicione à seção `<source>`:

```xml
<exclude>
    <directory>src/Resources</directory>
    <file>src/SomeFileToExclude.php</file>
</exclude>
```

## 📊 CI/CD Integration

### GitHub Actions

O workflow `.github/workflows/tests.yml` pode ser atualizado para incluir coverage:

```yaml
- name: Run tests with coverage
  run: |
    XDEBUG_MODE=coverage ./vendor/bin/pest --coverage --min=80 --coverage-clover=coverage/clover.xml

- name: Upload coverage to Codecov
  uses: codecov/codecov-action@v3
  with:
    files: ./coverage/clover.xml
    fail_ci_if_error: true
```

### Badge no README

Depois de configurar Codecov ou Coveralls:

```markdown
[![Coverage](https://codecov.io/gh/usuario/saci/branch/main/graph/badge.svg)](https://codecov.io/gh/usuario/saci)
```

## 🐛 Troubleshooting

### Xdebug não encontrado

```bash
# Instalar Xdebug via PECL
pecl install xdebug

# Verificar instalação
php -v | grep Xdebug
```

### Coverage muito lento

Use PCOV (mais rápido que Xdebug):

```bash
pecl install pcov

# Usar PCOV
php -d pcov.enabled=1 vendor/bin/pest --coverage
```

### "No code coverage driver available"

Certifique-se de usar `XDEBUG_MODE=coverage`:

```bash
XDEBUG_MODE=coverage ./vendor/bin/pest --coverage
```

## 📚 Recursos

- [Pest Coverage](https://pestphp.com/docs/coverage)
- [PHPUnit Coverage](https://docs.phpunit.de/en/10.5/code-coverage.html)
- [Xdebug Documentation](https://xdebug.org/docs/code_coverage)

---

**💡 Dica**: Execute `composer test:coverage-html` regularmente durante o desenvolvimento para acompanhar a cobertura do seu código!

