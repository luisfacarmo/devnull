# DevNull — Auditoria QA, Regressão e Plano de Estabilização

**Data:** 11/08/2026
**Versão auditada:** 0.2.0 (commit `2e659c8`, HEAD de master)
**Tag estável confirmada:** `v0.2.0-stable` (commit `273991d`)
**Auditor:** Kiro AI
**Escopo:** Código completo da app PHP + frontend Vue.js

---

## 1. Executive Summary

O DevNull é um MVP funcional com detecção de discos e mount/eject operacionais. O core path (detect → mount → eject) funciona no servidor de teste. Porém, a integração com Nextcloud Files (storage registration + scan) está parcialmente quebrada porque a abordagem via PHP API interna (`GlobalStoragesService`) foi introduzida mas ainda não validada end-to-end no servidor. Os IngestSteps (scan/dedup/classify) falham consistentemente porque chamam `occ` como subprocess dentro de request HTTP.

**Veredicto:** MVP parcialmente funcional. 3 bugs críticos (P1-P3) impedem uso completo. Funcionalidades base (detect/mount/eject) estão sólidas.

---

## 2. Baseline Atual

### Confirmado funcionando (testado no servidor 10-11/08/2026):

| Feature | Evidência |
|---------|-----------|
| Detecção de discos | Pendrive BÁRBARA detectado corretamente |
| UI carrega sem erros | Console limpo (apenas warnings de font CSP, não DevNull) |
| Mount via udisksctl | Pendrive monta em `/media/www-data/BÁRBARA` |
| Eject via udisksctl --force | Pendrive desmonta com sucesso |
| Botão Refresh | Atualiza lista de discos |
| Badge "Montado" | Aparece quando disco está montado |
| Warning udisks2 | Banner aparece quando udisks2 não disponível |
| App carrega sem udisks2 | Fallback NullMountStrategy funciona |
| App carrega sem tabelas DB | Controllers com try/catch retornam vazio |

### Parcialmente funcionando:

| Feature | Estado | Problema |
|---------|--------|----------|
| Storage registration | Storage criado, mas conteúdo 0kb | `scanUserFiles()` não indexa |
| Eject + unregister | Código existe | Não testado após último commit |

### Não funcionando:

| Feature | Estado | Problema |
|---------|--------|----------|
| Ingest pipeline (Processar) | 500 / exit 1 | Steps chamam `occ` via subprocess |
| Operation log | Vazio | Tabelas DB não existem |
| Migration | Nunca executou | NC marcou como já migrada |

---

## 3. Feature Matrix

| ID | Feature | Código | Rota | Frontend | Server Test | Status |
|----|---------|:------:|:----:|:--------:|:-----------:|--------|
| F1 | Disk detection | ✅ | GET /disks | ✅ | ✅ | DONE |
| F2 | Mount | ✅ | POST /mount | ✅ | ✅ | DONE |
| F3 | Eject | ✅ | POST /unmount | ✅ | ✅ | DONE |
| F4 | Storage register | ✅ | (in mount) | — | ⚠️ 0kb | PARTIAL |
| F5 | Storage unregister | ✅ | (in unmount) | — | ❌ untested | PARTIAL |
| F6 | Files scan | ✅ | (in register) | — | ❌ não indexa | BROKEN |
| F7 | Ingest scan | ✅ | POST /ingest | ✅ | ❌ exit 1 | BROKEN |
| F8 | Ingest dedup | ✅ | POST /ingest | ✅ | ❌ exit 1 | BROKEN |
| F9 | Ingest classify | ✅ | POST /ingest | ✅ | ❌ exit 1 | BROKEN |
| F10 | Operation log | ✅ | GET /logs | ✅ | ⚠️ vazio | DEGRADED |
| F11 | Status endpoint | ✅ | GET /status | — | ⚠️ vazio | DEGRADED |
| F12 | Warning banner | ✅ | (in /disks) | ✅ | ✅ | DONE |
| F13 | Fallback no udisks | ✅ | — | ✅ | ✅ | DONE |

---

## 4. P1–P9 Validation

| Bug | Status | Confirmação |
|-----|--------|-------------|
| P1 — 0kb após mount | **CONFIRMADO** | Storage aparece mas sem conteúdo |
| P2 — Eject não remove storage | **PARCIAL** | Código implementado (PHP API), não testado após OPcache issue |
| P3 — Pipeline occ falha | **CONFIRMADO** | Log: "Comando falhou (exit 1): php" |
| P4 — Migration não executou | **CONFIRMADO** | Tabelas não existem no DB |
| P5 — OPcache | **CONFIRMADO** | Código novo não faz efeito sem restart |
| P6 — Unicode path | **CORRIGIDO** | `udisksctl` output parseado corretamente |
| P7 — DI crash → 404 | **CORRIGIDO** | Lazy DI em todos controllers |
| P8 — AdminSettings | **CORRIGIDO** | Removido do info.xml |
| P9 — Migration duplicada | **CORRIGIDO** | Apenas `lib/Migration/` existe |

---

## 5. QA Audit

### Testes existentes: NENHUM

- Nenhum PHPUnit test
- Nenhum teste Jest/Vitest
- Nenhum teste de integração
- Nenhum smoke test
- Nenhum lint configurado (eslint declarado no package.json mas sem .eslintrc)

### Ferramentas recomendadas:

| Ferramenta | Cobre | Prioridade | Esforço |
|-----------|-------|:---:|:---:|
| `php -l` | Sintaxe PHP | P0 | Mínimo |
| PHPStan level 5 | Tipos, null safety | P1 | Baixo |
| `occ app:check-code` | NC API compliance | P0 | Mínimo |
| ESLint @nextcloud/eslint-config | JS/Vue | P2 | Baixo |
| PHPUnit + NC test framework | Unitários | P2 | Médio |

---

## 6. Security Audit

### SecureCommandRunner

| Aspecto | Estado | Risco |
|---------|--------|-------|
| Whitelist de comandos | ✅ 5 comandos | Baixo |
| `escapeshellarg()` em args | ✅ Aplicado | Baixo |
| `escapeshellcmd()` no comando | ✅ Aplicado | Baixo |
| Verifica `exec()` disponível | ✅ `isAvailable()` | Baixo |
| Device name validation | ✅ `/^[a-z0-9]+$/` | Baixo |

**Preocupações:**
1. `php` está na whitelist — permite chamar qualquer script PHP se alguém controlar os argumentos. Mitigação: argumentos vêm de código server-side, não do user.
2. `sudo` está na whitelist — mesmo risco. Mitigação: `SudoMountStrategy` controla os argumentos.
3. `escapeshellcmd()` + `escapeshellarg()` juntos podem ter interações inesperadas em edge cases.

**Veredicto:** Aceitável para MVP. Nenhum input do usuário chega ao `exec()` sem sanitização.

### Validação de input (frontend → backend):

| Endpoint | Param | Validação |
|----------|-------|-----------|
| POST /mount | device | `/^[a-z0-9]+$/` ✅ |
| POST /unmount | device | `/^[a-z0-9]+$/` ✅ |
| POST /ingest | device | Sem validação explícita ⚠️ |

**Ação necessária:** Adicionar validação de device no IngestController.

---

## 7. Compatibility Audit (NC 28-34)

| API/Classe usada | Disponível em | Risco |
|------------------|:---:|:---:|
| `OCSController` | NC 9+ | ✅ Seguro |
| `IDBConnection` | NC 9+ | ✅ Seguro |
| `IEventDispatcher` | NC 17+ | ✅ Seguro |
| `\OCP\Server::get()` | NC 27+ | ✅ Seguro |
| `IBootstrap` | NC 20+ | ✅ Seguro |
| `GlobalStoragesService` | NC 10+ | ✅ Seguro |
| `BackendService` | NC 10+ | ✅ Seguro |
| `StorageConfig` | NC 10+ | ✅ Seguro |
| `SimpleMigrationStep` | NC 13+ | ✅ Seguro |
| `IRootFolder::getUserFolder()` | NC 9+ | ✅ Seguro |
| `str_starts_with()` | PHP 8.0+ | ✅ (min PHP 8.2) |
| `str_contains()` | PHP 8.0+ | ✅ |
| `readonly` properties | PHP 8.1+ | ✅ |
| Named arguments | PHP 8.0+ | ✅ |
| Constructor promotion | PHP 8.0+ | ✅ |
| `match` expression | PHP 8.0+ | ✅ |

**Veredicto:** Todas as APIs são compatíveis com NC 28-34.

---

## 8. Golden Scenarios

| ID | Cenário | Pré-condição | Passos | Resultado esperado |
|----|---------|-------------|--------|-------------------|
| G001 | Mount → Files visível | Pendrive conectado, desmontado | Montar no DevNull | Storage no Files com conteúdo |
| G002 | Eject → Storage removido | Pendrive montado pelo DevNull | Ejetar no DevNull | Storage desaparece do Files |
| G003 | Unicode label | Pendrive "BÁRBARA" | Montar | Path correto `/media/www-data/BÁRBARA` |
| G004 | udisks2 ausente | Sem udisks2 | Carregar app | Banner amarelo, botão desabilitado, sem crash |
| G005 | Mount idempotente | Pendrive já montado | Clicar Mount | Erro amigável ou no-op |
| G006 | DB tables ausentes | Fresh install | Carregar app + usar | App funciona, logs vazios |
| G007 | Scan falha | Pendrive montado | Clicar Processar | Erro amigável, sem crash, app continua |

---

## 9. Regression Matrix

| Cenário | v0.2.0-stable | HEAD | Regressão? |
|---------|:---:|:---:|:---:|
| Detect disk | ✅ | ✅ | Não |
| Mount | ✅ | ✅ | Não |
| Eject | ✅ | ✅ | Não |
| Files com conteúdo | ⚠️ manual | ⚠️ 0kb | P1 persiste |
| Processar | ❌ | ❌ | P3 persiste |
| UI carrega | ✅ | ✅ | Não |
| Fallback no udisks | ✅ | ✅ | Não |

---

## 10. State Machine (Lifecycle do Dispositivo)

```
DISCONNECTED
    │ (USB plug)
    ▼
DETECTED (lsblk vê, mountpoint=null)
    │ (user clica Mount)
    ▼
MOUNTING (udisksctl mount em execução)
    │ (sucesso)
    ▼
MOUNTED (mountpoint != null)
    │ (StorageRegistrar.register)
    ▼
REGISTERED (storage ID existe no NC)
    │ (scan)
    ▼
READY (arquivos indexados e visíveis)
    │ (user clica Eject)
    ▼
EJECTING (unregister + unmount)
    │ (sucesso)
    ▼
DETECTED (volta ao início, sem mountpoint)
```

**Estados problemáticos atuais:**
- MOUNTED → REGISTERED funciona
- REGISTERED → READY falha (scan não indexa)
- EJECTING: unregister pode falhar se mountpoint já desmontado

---

## 11. Technical Debt

| Item | Impacto | Esforço |
|------|---------|---------|
| IngestSteps usam subprocess occ | P3 bloqueante | Alto |
| Migration não executou | P4 logs vazios | Baixo |
| `scanUserFiles()` apenas acessa folder (não escaneia) | P1 0kb | Médio |
| Sem testes automatizados | Risco de regressão | Alto |
| `lib/Db/Entity/` e `lib/Db/Mapper/` vazios | Código morto | Mínimo |
| `StatusTransportInterface` declarada mas não usada | Código morto | Mínimo |

---

## 12. Quick Wins

| # | Ação | Resolve | Esforço |
|---|------|---------|---------|
| QW1 | Forçar migration no servidor | P4 | 1 comando |
| QW2 | Deletar pastas vazias (`Db/Entity/`, `Db/Mapper/`, `Status/`) | Limpeza | 1 minuto |
| QW3 | Remover `StatusTransportInterface` (não usada) | Limpeza | 1 minuto |
| QW4 | Adicionar validação de device no IngestController | Segurança | 2 linhas |

---

## 13. Roadmap Reconciliation

| Task original | Estado real | QA necessário | Alteração no plano | Prioridade |
|---------------|------------|---------------|-------------------|:---:|
| Sprint 0 — Scaffolding | DONE | — | — | — |
| Sprint 1 — DiskDetection | DONE | regression test | G001, G003, G004, G006 | — |
| Sprint 2 — Mount | DONE_NEEDS_REGRESSION | E2E G001, G002 | Corrigir P1, P2 | P0 |
| Sprint 3 — Polish | PARTIAL | — | Concluir após P1-P2 | P1 |
| Sprint 4 — Ingest Pipeline | REQUIRES_REWORK | — | P3: migrar pra BackgroundJob | P2 |
| Sprint 5 — Daemon | NOT_STARTED | — | Manter no roadmap | P3 |
| Sprint 6 — Automação | NOT_STARTED | — | Manter no roadmap | P4 |

---

## 14. Plano de Correção

### FASE 0 — Baseline (esta sessão)
- [x] Ler todo o código
- [x] Documentar estado real
- [x] Identificar bugs
- [x] Produzir relatório

### FASE 1 — Quick Wins
- [ ] QW1: Forçar migration (`occ upgrade` ou nova migration)
- [ ] QW2-QW4: Limpeza de código morto

### FASE 2 — P1 (Scan/Indexação)
- [ ] Substituir `scanUserFiles()` por Scanner real via PHP API
- [ ] Testar: mount → register → scan → conteúdo visível
- [ ] Golden: G001

### FASE 3 — P2 (Eject lifecycle)
- [ ] Garantir que `removeExternalStorageForDevice` funciona após OPcache clear
- [ ] Testar: eject → storage removido → pasta desaparece
- [ ] Golden: G002

### FASE 4 — P3 (Pipeline)
- [ ] Converter IngestSteps para usar PHP API interna (Scanner) ou BackgroundJob
- [ ] Não chamar `occ` como subprocess
- [ ] Testar: Processar → steps executam sem erro
- [ ] Golden: G007

### FASE 5 — Regression Hardening
- [ ] PHPStan level 5
- [ ] `occ app:check-code devnull`
- [ ] Golden scenarios automatizados (onde possível)

### FASE 6 — Continuar Roadmap
- [ ] Sprint 5 (Daemon) quando P1-P4 estiverem estáveis

---

## 15. Próxima Ação

**TASK:** FASE 1, QW1 — Forçar execução da migration no servidor

**Objetivo:** Criar tabelas `oc_devnull_*` no banco

**Comando no servidor:**
```bash
sudo -u www-data php /var/www/nextcloud/occ migrations:execute devnull Version000100Date20260806
```

Se esse comando não existir na versão NC34, alternativa:
```bash
sudo -u www-data php /var/www/nextcloud/occ app:disable devnull
sudo -u www-data php /var/www/nextcloud/occ app:enable devnull
# Verificar:
sudo mysql nextcloud -e "SHOW TABLES LIKE 'oc_devnull%';"
```

**Critério de aceite:** Tabelas `oc_devnull_disks`, `oc_devnull_operations`, `oc_devnull_mounts` existem.

**Risco:** Baixo (apenas cria tabelas novas, não altera existentes)

**Rollback:** Não necessário (tabelas vazias não afetam nada)

---

*Fim do relatório de auditoria.*
