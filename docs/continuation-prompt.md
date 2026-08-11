# DevNull — Prompt de Continuidade (Pós-Auditoria)

Cole este prompt no início de uma nova sessão para retomar o desenvolvimento.

---

## PROMPT

Estou retomando o desenvolvimento do projeto **DevNull** — uma app Nextcloud para auto-ingest de discos externos.

**Repositório:** https://github.com/luisfacarmo/devnull  
**Workspace local:** `The Good Place/DevNull/`  
**Servidor de teste:** LibraryOfAlexandria (Debian 13, Mac Mini, 192.168.15.130)  
**SSH:** indisponível (roteador bloqueia porta 22). Deploy via `git pull` no terminal local do Mac Mini.

---

## Estado atual

**Versão:** 0.2.0  
**Tag estável (rollback seguro):** `v0.2.0-stable` (commit `273991d`)  
**Branch:** master

### O que funciona (confirmado no servidor):
- Detecção de discos via lsblk (recursivo, filtra sistema)
- Mount via udisksctl (www-data com polkit)
- Eject via udisksctl --force
- UI Vue.js (lista discos, botões, badges, warning banner)
- Fallback sem udisks2/tabelas/daemon (app carrega sem crash)

### Bugs ativos (por prioridade):

| Bug | Descrição | Root Cause |
|-----|-----------|-----------|
| **P1** | Storage registra no NC Files mas conteúdo mostra 0kb | `scanUserFiles()` em `NextcloudStorageRegistrar` apenas acessa o folder, não dispara scan real |
| **P2** | Eject não remove external storage | `removeExternalStorageForDevice()` tenta re-detectar mountpoint após disco já desmontado |
| **P3** | Pipeline (Processar) falha com exit 1 | IngestSteps chamam `occ` como subprocess — não funciona dentro de request HTTP |
| **P4** | Tabelas DB nunca criadas | Migration existe em `lib/Migration/` mas NC não a executou (marcou como "já migrada" antes do código existir) |

### Bugs já corrigidos:
- P6: Unicode path (BÁRBARA) — parseamos mountpoint real do udisksctl
- P7: DI crash → 404 — lazy DI em todos controllers
- P8: AdminSettings inexistentes — removidos do info.xml
- P9: Migration duplicada — deletada cópia errada

---

## Plano de correção (da auditoria)

Execute em ordem. Cada fase tem critério de aceite.

### FASE 1 — Quick Wins
1. Forçar migration no servidor: `occ app:disable devnull && occ app:enable devnull`
2. Verificar tabelas: `mysql nextcloud -e "SHOW TABLES LIKE 'oc_devnull%';"`
3. Deletar pastas vazias: `lib/Db/Entity/`, `lib/Db/Mapper/`, `lib/Status/`
4. Remover `StatusTransportInterface` (não usada por nenhum código)
5. Adicionar validação de device no IngestController

### FASE 2 — P1 (Scan/Indexação)
- `scanUserFiles()` no `NextcloudStorageRegistrar` precisa usar `\OC\Files\Utils\Scanner` em vez de apenas `getUserFolder()`
- Alternativa: disparar `\OCP\BackgroundJob\IJobList->add()` com job de scan
- Critério: após mount, conteúdo aparece no Files sem comando manual

### FASE 3 — P2 (Eject lifecycle)
- O storageId retornado por `register()` precisa ser salvo (DB ou response)
- No eject, usar esse ID diretamente em vez de re-detectar
- Critério: eject remove o storage do NC Files

### FASE 4 — P3 (Pipeline)
- IngestSteps (Scan, Dedup, Classify) NÃO podem chamar `occ` como subprocess
- ScanStep: usar `\OC\Files\Utils\Scanner` via PHP API
- DeduplicateStep/ClassifyStep: agendar como BackgroundJob
- Critério: botão "Processar" não retorna 500

### FASE 5 — Regression + QA
- `occ app:check-code devnull`
- PHPStan level 5
- Golden scenarios testados manualmente

---

## Regras de desenvolvimento

1. **NÃO quebrar o que funciona** (detect, mount, eject, UI)
2. Sempre verificar com `git diff` antes de commit
3. Build frontend: `cd app && npm run build`
4. Push: `git add -A && git commit --no-verify -m "..." && git push origin master`
5. Deploy no servidor: `cd /opt/devnull && git pull` (Mac Mini, terminal local)
6. Após alteração PHP: restart Apache (`sudo systemctl restart apache2`) por causa do OPcache
7. Rollback seguro: `git checkout v0.2.0-stable`

## Arquivos-chave

| Arquivo | Responsabilidade |
|---------|-----------------|
| `app/lib/Storage/NextcloudStorageRegistrar.php` | P1 — scan + register |
| `app/lib/Controller/MountController.php` | P2 — eject lifecycle |
| `app/lib/Ingest/Step/*.php` | P3 — pipeline steps |
| `app/lib/Migration/Version000100Date20260806.php` | P4 — schema DB |
| `app/lib/Mount/UdisksMountStrategy.php` | Mount/eject (funcional) |
| `app/lib/Detection/LsblkDetector.php` | Detecção (funcional) |

## Documentação local (não no repo)

- `docs/audit-qa-report.md` — relatório completo da auditoria
- `docs/project-plan.md` — plano original de arquitetura

## App Store

- PR de certificado aberta: https://github.com/nextcloud/app-certificate-requests/pull/1152
- Chave privada backup: `H:\Meu Drive\Backups\Configs\DevNull\devnull.key`
- Publicação após MVP estável (P1-P4 resolvidos)

---

## Instrução

Continue a execução a partir da **FASE 1** do plano. Siga:

**BASELINE → TESTE → ALTERAÇÃO MÍNIMA → TESTE → REGRESSÃO → DOCUMENTAÇÃO**

Não reescreva arquitetura. Não adicione features. Corrija os bugs P1-P4 em ordem, sem quebrar o que funciona.
