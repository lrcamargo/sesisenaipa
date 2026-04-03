#!/bin/bash
# =============================================================================
# deploy_cron.sh
#
# Chamado pelo cron a cada 30 minutos.
# Verifica o estado de cada branch (main e dev) com detecção de direção:
#
#   atualizado    : servidor sincronizado com o GitHub
#   atras         : GitHub tem commits novos — deploy recomendado
#   adiantado     : servidor tem commits não enviados ao GitHub
#   divergente    : ambos têm commits exclusivos — atenção necessária
#   sem_branch    : branch não existe localmente
#   sem_remoto    : branch local não foi enviada ao GitHub ainda
#
# NÃO faz git pull automaticamente — apenas registra no log.
# O deploy deve ser feito manualmente pelo botão em deploy.php.
#
# ─── INSTALAÇÃO DO CRON ───────────────────────────────────────────────────
# Execute como usuário "suporte":
#   sudo crontab -u suporte -e
#
# Adicione a linha abaixo para rodar a cada 30 minutos:
#   */30 * * * * /var/www/html/scripts/deploy_cron.sh >> /var/www/html/logs/deploy_cron.log 2>&1
#
# Para testar manualmente:
#   sudo -u suporte bash /var/www/html/scripts/deploy_cron.sh
# =============================================================================

REPO_PATH="/var/www/html"
BRANCHES=("main" "dev")

mkdir -p "$REPO_PATH/logs"

echo "========================================"
echo "Verificação: $(date '+%d/%m/%Y %H:%M:%S')"
echo "========================================"

cd "$REPO_PATH" || {
    echo "ERRO: Não foi possível acessar $REPO_PATH"
    exit 1
}

# Busca atualizações remotas sem aplicar nada
git fetch origin --quiet 2>&1

for BRANCH in "${BRANCHES[@]}"; do

    echo ""
    echo "── Branch: $BRANCH ──"

    # Verifica se a branch existe localmente
    if ! git rev-parse --verify "$BRANCH" > /dev/null 2>&1; then
        echo "  ➖ sem_branch  — Branch não existe localmente."
        echo "     Para criar: git checkout -b $BRANCH origin/$BRANCH"
        continue
    fi

    # Verifica se a branch remota existe
    if ! git rev-parse --verify "origin/$BRANCH" > /dev/null 2>&1; then
        LOCAL=$(git rev-parse --short "$BRANCH" 2>/dev/null)
        echo "  ➖ sem_remoto  — Branch local não enviada ao GitHub (local: $LOCAL)."
        echo "     Para enviar: git push -u origin $BRANCH"
        continue
    fi

    # Hashes para exibição
    LOCAL=$(git rev-parse --short "$BRANCH")
    REMOTO=$(git rev-parse --short "origin/$BRANCH")

    # Commits que o REMOTO tem e o LOCAL não tem (local está atrás)
    ATRAS=$(git rev-list --count "$BRANCH".."origin/$BRANCH")

    # Commits que o LOCAL tem e o REMOTO não tem (local está à frente)
    ADIANTADO=$(git rev-list --count "origin/$BRANCH".."$BRANCH")

    if [ "$ATRAS" -eq 0 ] && [ "$ADIANTADO" -eq 0 ]; then
        echo "  ✅ atualizado  — Servidor sincronizado com o GitHub (hash: $LOCAL)."

    elif [ "$ATRAS" -gt 0 ] && [ "$ADIANTADO" -eq 0 ]; then
        echo "  ⚠️  atras       — $ATRAS commit(s) do GitHub ainda não aplicado(s) no servidor."
        echo "     Local: $LOCAL | Remoto: $REMOTO"
        echo "     Acesse deploy.php para aplicar a atualização."

    elif [ "$ADIANTADO" -gt 0 ] && [ "$ATRAS" -eq 0 ]; then
        echo "  ℹ️  adiantado   — $ADIANTADO commit(s) no servidor ainda não enviado(s) ao GitHub."
        echo "     Local: $LOCAL | Remoto: $REMOTO"
        echo "     Rode 'git push origin $BRANCH' quando estiver pronto."

    else
        echo "  ❌ divergente  — $ADIANTADO commit(s) locais e $ATRAS commit(s) remotos exclusivos."
        echo "     Local: $LOCAL | Remoto: $REMOTO"
        echo "     É necessário merge ou rebase antes do deploy."
    fi

done

echo ""
echo "========================================"
echo ""