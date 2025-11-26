#!/bin/bash

# Script para gerar relatórios de cobertura de código
# Uso: ./coverage.sh [html|text|all]

set -e

echo "🧪 Gerando relatórios de cobertura de código..."
echo ""

MODE=${1:-html}

case $MODE in
    html)
        echo "📊 Gerando relatório HTML..."
        XDEBUG_MODE=coverage ./vendor/bin/pest --coverage --coverage-html=coverage/html
        echo ""
        echo "✅ Relatório HTML gerado em: coverage/html/index.html"
        echo "🌐 Abrir no navegador: open coverage/html/index.html"
        ;;
    text)
        echo "📝 Gerando relatório de texto..."
        XDEBUG_MODE=coverage ./vendor/bin/pest --coverage --coverage-text
        ;;
    all)
        echo "📊 Gerando todos os relatórios..."
        XDEBUG_MODE=coverage ./vendor/bin/pest --coverage \
            --coverage-html=coverage/html \
            --coverage-clover=coverage/clover.xml \
            --coverage-text
        echo ""
        echo "✅ Relatórios gerados:"
        echo "   - HTML: coverage/html/index.html"
        echo "   - Clover XML: coverage/clover.xml"
        echo "   - JUnit XML: coverage/junit.xml"
        ;;
    *)
        echo "❌ Uso: ./coverage.sh [html|text|all]"
        exit 1
        ;;
esac

echo ""
echo "✨ Concluído!"

