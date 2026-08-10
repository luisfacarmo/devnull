# DevNull — Plano de Projeto

> **Tagline:** "Where your data goes to live."
> **Arquitetura:** Hybrid Modular (PHP App + External Daemon)
> **Repositório:** devnull
> **Autor:** Luis Carmo
> **Licença:** AGPL-3.0
> **Status:** Planejamento
> **Versão do documento:** 2.0.0

---

## 1. Visão Geral

### Elevator Pitch

DevNull é uma app Nextcloud que transforma o servidor pessoal num ponto central
de ingestão de discos externos. Conectou o HD? A app detecta, mostra na
interface, e com um clique monta, escaneia, deduplica e classifica — sem
precisar de terminal.

### Público-alvo

- Usuários de homelab/home server que acumulam HDs com dados históricos
- Quem está migrando de armazenamento fragmentado para um ponto central
- Operadores que querem workflow visual sem depender de CLI

### Diferencial

Nenhuma app Nextcloud existente faz o ciclo completo:
**detectar -> montar -> registrar -> escanear -> classificar -> desmontar**.
DevNull fecha esse gap com uma interface integrada ao Nextcloud.

### Ironia do Nome

`/dev/null` é onde dados vão para morrer no Unix. Esta app faz o oposto:
resgata dados esquecidos em HDs gaveta e dá a eles uma vida organizada.

---

## 2. Princípios Arquiteturais

Herdados da filosofia "Lunar Room" e adaptados para open-source:

### P1. Capabilities Before Screens

Definir o que o sistema FAZ (capabilities) antes de como MOSTRA (UI).
Cada capability é independente, testável e substituível.

### P2. Domain Separation (Strict Boundaries)

Cada módulo tem responsabilidade exclusiva. Nenhum módulo invade o domínio
de outro. Se precisa de algo fora do seu escopo, comunica via interface.

| Domínio | Responsável | Nunca faz |
|---------|-------------|-----------|
| Detecção de hardware | DiskDetection capability | Montar, escanear |
| Montagem/desmontagem | Mount capability | Detectar, registrar storage |
| Registro no Nextcloud | Storage capability | Montar, escanear |
| Scan e classificação | Ingest capability | Montar, detectar |
| Persistência | Repository layer | Lógica de negócio |
| Comunicação com daemon | Bridge capability | Tudo acima |

### P3. Incremental Evolution

Começar com o que funciona. Enriquecer depois. O MVP funciona sem daemon
(detecção manual via refresh). O daemon é um ENHANCER, não um requisito.

### P4. Capability-Based Abstraction

Código conhece capabilities, não implementações. Se a forma de montar muda
(udisks2 -> sudo mount -> Docker volume), apenas a Strategy muda. O resto
do sistema não sabe e não se importa.

### P5. Multi-Tenant by Design

Desde o dia 1, o sistema trata cada user como entidade separada. Mesmo que
hoje exista um admin, a arquitetura não assume single-user em nenhum ponto.

### P6. No Monoliths

Cada componente pode evoluir, ser substituído ou removido sem afetar os
outros. O PHP app é o "rosto" (UI/API). O daemon é o "músculo" (hardware).
Comunicam via contrato (REST/socket). Se um morre, o outro continua útil.

---

## 3. Decisões de Design (Consolidadas)

| # | Decisão | Escolha | Justificativa |
|---|---------|---------|---------------|
| D1 | Mountpoint | `/media/devnull/<label>` | Padrão userspace, compatível com udisks2. Ficheiro `.devnull` como marcador. |
| D2 | Permissões | Admin monta; users veem | Separação de responsabilidade. External storage visibility por user. |
| D3 | Registro de storage | API PHP interna (`GlobalStoragesService`) | Instantâneo, retorna ID, permite set visibility. |
| D4 | App ID | `devnull` / `OCA\DevNull` | Curto, memorável, alinhado com branding. |
| D5 | Multi-tenant | Sim, desde o MVP | Escalabilidade. Cada user gere seus discos com permissão. |
| D6 | Licença | AGPL-3.0 | Padrão Nextcloud. Garante contribuições de volta. |
| D7 | Sistema de mount | Strategy Pattern | Interface `MountStrategy` + implementações (Udisks, Sudo, futuras). |
| D8 | Status real-time | Polling + abstração `StatusTransport` | MVP com polling 5s. Migra para SSE sem rewrite. |
| D9 | Persistência | Schema normalizado (5 tabelas) | Relacional, extensível, queries poderosas. |
| D10 | Arquitetura | Híbrida (PHP App + Daemon externo) | App = UI/API/DB. Daemon = hardware/jobs. Daemon opcional no MVP. |

---

## 4. Arquitetura Técnica

### 4.1 Visão de Alto Nível

```
┌───────────────────────────────────────────────────────────────────┐
│                    NEXTCLOUD (PHP App)                             │
│                                                                   │
│  ┌─────────┐  ┌──────────┐  ┌──────────┐  ┌──────────────────┐  │
│  │ Vue.js  │  │   API    │  │ Services │  │   Repositories   │  │
│  │ Frontend│→ │Controllers│→ │(Capabilities)│→ │  (DB Layer)  │  │
│  └─────────┘  └──────────┘  └──────────┘  └──────────────────┘  │
│                                   ↕                               │
│                          ┌────────────────┐                       │
│                          │  Bridge Layer  │                       │
│                          │ (REST/Socket)  │                       │
│                          └───────┬────────┘                       │
└──────────────────────────────────┼────────────────────────────────┘
                                   ↕
┌──────────────────────────────────┼────────────────────────────────┐
│                    DAEMON (Python/Go) — OPTIONAL                   │
│                                                                   │
│  ┌──────────────┐  ┌──────────────┐  ┌────────────────────────┐  │
│  │ HW Detection │  │ Mount Engine │  │ Background Workers     │  │
│  │ (udev/poll)  │  │ (strategies) │  │ (scan, classify, dedup)│  │
│  └──────────────┘  └──────────────┘  └────────────────────────┘  │
└───────────────────────────────────────────────────────────────────┘
```

### 4.2 Capabilities Map

Cada capability é uma unidade funcional independente com interface definida:

| Capability | Interface PHP | Implementações | Domínio |
|-----------|---------------|----------------|---------|
| DiskDetection | `DiskDetectorInterface` | `LsblkDetector`, `DaemonBridgeDetector` | Listar block devices |
| Mount | `MountStrategyInterface` | `UdisksMountStrategy`, `SudoMountStrategy` | Montar/desmontar |
| StorageRegistration | `StorageRegistrarInterface` | `NextcloudStorageRegistrar` | Registrar external storage |
| Ingest | `IngestPipelineInterface` | `ScanStep`, `DeduplicateStep`, `ClassifyStep` | Pipeline de processamento |
| StatusReporting | `StatusTransportInterface` | `PollingTransport`, `SSETransport` | Reportar progresso |
| DaemonBridge | `DaemonClientInterface` | `HttpDaemonClient`, `SocketDaemonClient`, `NullClient` | Comunicar com daemon |

**Regra:** Nenhum Controller conhece implementação concreta. Só interfaces.
O DI container do Nextcloud resolve as dependências.

### 4.3 Estrutura de Módulos (PHP App)

```
lib/
├── AppInfo/
│   └── Application.php              ← Bootstrap + DI bindings
├── Capability/                       ← Interfaces de cada capability
│   ├── DiskDetectorInterface.php
│   ├── MountStrategyInterface.php
│   ├── StorageRegistrarInterface.php
│   ├── IngestPipelineInterface.php
│   ├── StatusTransportInterface.php
│   └── DaemonClientInterface.php
├── Detection/                        ← Domínio: Detecção
│   ├── LsblkDetector.php
│   └── DaemonBridgeDetector.php
├── Mount/                            ← Domínio: Montagem
│   ├── UdisksMountStrategy.php
│   ├── SudoMountStrategy.php
│   └── MountStrategyFactory.php
├── Storage/                          ← Domínio: Registro NC
│   └── NextcloudStorageRegistrar.php
├── Ingest/                           ← Domínio: Pipeline
│   ├── IngestPipeline.php
│   ├── Step/
│   │   ├── ScanStep.php
│   │   ├── DeduplicateStep.php
│   │   └── ClassifyStep.php
│   └── IngestStepInterface.php
├── Bridge/                           ← Domínio: Comunicação com daemon
│   ├── HttpDaemonClient.php
│   ├── SocketDaemonClient.php
│   └── NullDaemonClient.php
├── Controller/                       ← API Layer
│   ├── DiskController.php
│   ├── MountController.php
│   ├── IngestController.php
│   └── StatusController.php
├── Db/                               ← Repository Layer
│   ├── Entity/
│   │   ├── Disk.php
│   │   ├── Operation.php
│   │   └── Mount.php
│   ├── Mapper/
│   │   ├── DiskMapper.php
│   │   ├── OperationMapper.php
│   │   └── MountMapper.php
│   └── Migration/
│       └── Version000100Date*.php
├── BackgroundJob/                    ← Jobs assíncronos
│   ├── IngestJob.php
│   └── DiskPollingJob.php
├── Event/                            ← Event-driven
│   ├── DiskMountedEvent.php
│   ├── DiskUnmountedEvent.php
│   ├── IngestCompletedEvent.php
│   └── DiskDetectedEvent.php
├── Listener/                         ← Reações a eventos
│   ├── TriggerScanOnMount.php
│   └── NotifyOnIngestComplete.php
└── Command/                          ← Shell interaction
    └── SecureCommandRunner.php
```

### 4.4 Estrutura do Daemon (Python)

```
devnull-daemon/
├── devnull_daemon/
│   ├── __init__.py
│   ├── main.py                  ← Entrypoint
│   ├── config.py                ← Configuração (YAML/env)
│   ├── api/
│   │   ├── __init__.py
│   │   └── server.py            ← REST API (FastAPI/Flask)
│   ├── detection/
│   │   ├── __init__.py
│   │   ├── poller.py            ← Polling via lsblk (fallback)
│   │   └── udev_monitor.py     ← Real-time via pyudev
│   ├── mount/
│   │   ├── __init__.py
│   │   ├── strategy.py          ← Interface base
│   │   ├── udisks.py            ← UdisksMountStrategy
│   │   └── sudo_mount.py        ← SudoMountStrategy
│   ├── workers/
│   │   ├── __init__.py
│   │   ├── scan_worker.py
│   │   ├── dedup_worker.py
│   │   └── classify_worker.py
│   └── models/
│       ├── __init__.py
│       └── disk.py              ← Dataclasses
├── tests/
│   ├── test_detection.py
│   ├── test_mount.py
│   └── test_workers.py
├── systemd/
│   └── devnull-daemon.service   ← Unit file
├── pyproject.toml
├── README.md
└── .env.example
```

### 4.5 Comunicação App <-> Daemon

**Contrato REST entre os dois componentes:**

| Método | Endpoint (daemon) | Descrição |
|--------|-------------------|-----------|
| GET | `/api/v1/disks` | Lista discos detectados |
| POST | `/api/v1/mount` | Solicita mount de device |
| POST | `/api/v1/unmount` | Solicita unmount |
| GET | `/api/v1/status` | Status de operações ativas |
| POST | `/api/v1/ingest` | Dispara pipeline de ingest |
| GET | `/api/v1/health` | Health check do daemon |

**Fallback sem daemon:** Se o daemon não está disponível, a app PHP
faz tudo localmente via `SecureCommandRunner` (capacidade reduzida:
sem hotplug, sem workers paralelos, mas funcional).

---

## 5. Schema de Dados (Normalizado)

### Fase 1 (MVP)

```sql
-- Discos conhecidos pelo sistema
CREATE TABLE oc_devnull_disks (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    serial      VARCHAR(255) NOT NULL,
    label       VARCHAR(255),
    model       VARCHAR(255),
    fstype      VARCHAR(50),
    size_bytes  BIGINT,
    first_seen  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(serial)
);

-- Operações executadas
CREATE TABLE oc_devnull_operations (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    disk_id      INTEGER NOT NULL REFERENCES oc_devnull_disks(id),
    user_id      VARCHAR(64) NOT NULL,
    type         VARCHAR(50) NOT NULL,  -- mount, unmount, scan, dedup, classify
    status       VARCHAR(20) NOT NULL,  -- pending, running, completed, failed
    started_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at  TIMESTAMP,
    result_json  TEXT,
    error_msg    TEXT
);

-- Mounts ativos
CREATE TABLE oc_devnull_mounts (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    disk_id      INTEGER NOT NULL REFERENCES oc_devnull_disks(id),
    user_id      VARCHAR(64) NOT NULL,
    storage_id   INTEGER,              -- NC external storage ID
    mountpoint   VARCHAR(512) NOT NULL,
    mounted_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(disk_id)
);
```

### Fase 2 (Integrações)

```sql
-- Pipelines configuráveis por user
CREATE TABLE oc_devnull_pipelines (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     VARCHAR(64) NOT NULL,
    name        VARCHAR(255) NOT NULL,
    steps_json  TEXT NOT NULL,         -- ["scan", "dedup", "classify"]
    auto_trigger BOOLEAN DEFAULT FALSE,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Configurações por user
CREATE TABLE oc_devnull_user_configs (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id  VARCHAR(64) NOT NULL,
    key      VARCHAR(255) NOT NULL,
    value    TEXT,
    UNIQUE(user_id, key)
);
```

---

## 6. Requisitos Funcionais

### Fase 1 — MVP (sem daemon, funcional standalone)

| # | Capability | Requisito |
|---|-----------|-----------|
| F1.1 | DiskDetection | Listar block devices via `lsblk --json` (partições não montadas) |
| F1.2 | DiskDetection | Interface listando discos: nome, tamanho, filesystem, status |
| F1.3 | Mount | Montar disco em `/media/devnull/<label>` via MountStrategy |
| F1.4 | Mount | Ficheiro `.devnull` como marcador no mountpoint |
| F1.5 | StorageRegistration | Registrar como external storage via GlobalStoragesService |
| F1.6 | StorageRegistration | Configurar visibilidade por user (D2: admin monta, users veem) |
| F1.7 | Mount | Desmontar e remover external storage ao clicar "Ejetar" |
| F1.8 | StatusReporting | Indicador visual de status (PollingTransport, 5s) |
| F1.9 | Persistence | Log de operações (tabela `oc_devnull_operations`) |
| F1.10 | Persistence | Registro de discos conhecidos (tabela `oc_devnull_disks`) |

### Fase 2 — Integrações + Daemon básico

| # | Capability | Requisito |
|---|-----------|-----------|
| F2.1 | Ingest | Pipeline: Scan step (dispara `occ files:scan`) |
| F2.2 | Ingest | Pipeline: Deduplicate step (integra Duplicate Finder) |
| F2.3 | Ingest | Pipeline: Classify step (integra Recognize) |
| F2.4 | Ingest | Pipeline configurável por user (tabela `oc_devnull_pipelines`) |
| F2.5 | StatusReporting | Barra de progresso para pipelines |
| F2.6 | DaemonBridge | App comunica com daemon via REST (se disponível) |
| F2.7 | DaemonBridge | Fallback gracioso se daemon offline (NullDaemonClient) |
| F2.8 | Event | Notificação Nextcloud ao completar pipeline |

### Fase 3 — Automação e Auto-detect

| # | Capability | Requisito |
|---|-----------|-----------|
| F3.1 | DiskDetection | Auto-detect via daemon (udev monitor real-time) |
| F3.2 | Ingest | Auto-trigger: pipeline roda automaticamente ao montar |
| F3.3 | Persistence | Histórico completo de discos processados |
| F3.4 | StatusReporting | Dashboard com métricas (espaço, duplicatas, classificações) |
| F3.5 | Mount | Auto-mount por serial/label (regras configuráveis) |
| F3.6 | DiskDetection | Health check via SMART data (smartctl) |
| F3.7 | StatusReporting | Migrar para SSETransport (substituir polling) |

---

## 7. Requisitos Não-Funcionais

### Segurança

| # | Requisito | Princípio |
|---|-----------|-----------|
| NF1 | Toda shell call via `SecureCommandRunner` com whitelist + escapeshellarg | P4 |
| NF2 | Admin monta; users com permissão veem. Capability check por ação. | P5 |
| NF3 | Paths de mount restritos a `/media/devnull/` — validação regex | P2 |
| NF4 | Output de `lsblk` parseado como JSON estruturado | P4 |
| NF5 | Rate limiting em operações de mount (1 op/device/10s) | P6 |
| NF6 | Daemon autentica com app via token (não exposto) | P6 |
| NF7 | Device names validados: `/^[a-z]+[0-9]*$/` | P2 |

### Performance

| # | Requisito | Princípio |
|---|-----------|-----------|
| NF8 | Detecção < 2s | P3 |
| NF9 | Operações longas SEMPRE async (BackgroundJob ou daemon worker) | P6 |
| NF10 | Frontend nunca bloqueia — StatusTransport abstrai o mecanismo | P4 |
| NF11 | Polling com exponential backoff quando idle | P3 |

### Compatibilidade

| # | Requisito |
|---|-----------|
| NF12 | Nextcloud 28+ |
| NF13 | PHP 8.2+ |
| NF14 | Filesystems: ext4, NTFS, exFAT, HFS+, FAT32 |
| NF15 | Debian 12+ / Ubuntu 22.04+ |
| NF16 | Daemon: Python 3.11+ (opcional) |

---

## 8. Riscos e Mitigações

| # | Risco | Prob. | Impacto | Mitigação | Princípio |
|---|-------|-------|---------|-----------|-----------|
| R1 | `exec()` desabilitado em PHP | Média | Alto | Fallback via daemon; documentar requisito; verificar no boot | P3, P6 |
| R2 | www-data sem permissão de mount | Alta | Alto | Strategy Pattern: UdisksMountStrategy (polkit) ou SudoMountStrategy | P4 |
| R3 | Filesystem sem driver | Média | Baixo | Detectar fstype, reportar, listar pacotes necessários | P3 |
| R4 | Disco corrompido trava | Baixa | Médio | Timeout + health check (SMART). Isola no daemon. | P6 |
| R5 | Race condition multi-user | Média | Médio | Lock por device (DB). Status "em operação" visível. | P5 |
| R6 | NC External Storage API muda | Baixa | Médio | Abstração: `StorageRegistrarInterface`. Só 1 classe muda. | P4 |
| R7 | Scan timeout em disco grande | Alta | Médio | Background Job obrigatório. Nunca síncrono. | P6 |
| R8 | Daemon e App desincronizados | Média | Médio | Health check periódico. NullDaemonClient como fallback. | P3, P6 |
| R9 | Contribuidor cria acoplamento | Média | Alto | Interfaces obrigatórias. PR review contra princípios. CI valida. | P2, P6 |

---

## 9. Stack Tecnológico

### PHP App (Nextcloud)

| Componente | Tecnologia |
|-----------|------------|
| Runtime | PHP 8.2+ |
| Framework | Nextcloud App Framework 28+ |
| DI | Nextcloud DI Container (auto-wire via interfaces) |
| ORM | Nextcloud QBQueryBuilder + Entity/Mapper |
| Background Jobs | Nextcloud IJobList (cron) |
| Events | Nextcloud IEventDispatcher |
| Shell | SecureCommandRunner (whitelist + escape) |
| Frontend | Vue.js 2.7 + @nextcloud/vue + Webpack 5 |

### Daemon (Python)

| Componente | Tecnologia |
|-----------|------------|
| Runtime | Python 3.11+ |
| API Framework | FastAPI (async, leve) |
| Hardware detection | pyudev (real-time) / subprocess lsblk (fallback) |
| Mount | subprocess (udisksctl / mount) |
| Workers | asyncio tasks ou threading |
| Config | Pydantic settings (.env) |
| Deploy | systemd unit |

### Dependências do Servidor

```bash
# Obrigatórios (para app PHP funcionar standalone)
sudo apt install udisks2 util-linux

# Para filesystems
sudo apt install ntfs-3g exfatprogs hfsprogs

# Para daemon (opcional)
sudo apt install python3 python3-pip python3-venv

# Para health check (Fase 3)
sudo apt install smartmontools
```

---

## 10. Milestones

### Sprint 0 — Foundations (1 semana)

- [ ] Scaffolding: info.xml, Application.php, routes, composer, package.json
- [ ] Capability interfaces definidas (todas as 6)
- [ ] DB Migrations (3 tabelas MVP)
- [ ] App instalável via occ (empty shell)
- [ ] Polkit rule para udisks2
- [ ] CI básico (lint PHP + JS)

### Sprint 1 — DiskDetection Capability (1 semana)

- [ ] SecureCommandRunner com whitelist
- [ ] LsblkDetector (implementa DiskDetectorInterface)
- [ ] DiskController + rota GET /api/v1/disks
- [ ] DiskMapper + persistência de discos conhecidos
- [ ] Frontend: DiskList.vue (read-only)
- [ ] Testes unitários do detector

### Sprint 2 — Mount Capability (2 semanas)

- [ ] MountStrategyInterface + UdisksMountStrategy
- [ ] MountStrategyFactory (auto-detect disponível)
- [ ] NextcloudStorageRegistrar (registra + set visibility)
- [ ] MountController + rotas POST mount/unmount
- [ ] Ficheiro `.devnull` criado no mountpoint
- [ ] Event: DiskMountedEvent / DiskUnmountedEvent
- [ ] Frontend: botões montar/desmontar no DiskCard
- [ ] Testes de integração

### Sprint 3 — StatusReporting + Polish (1 semana)

- [ ] StatusTransportInterface + PollingTransport
- [ ] StatusController + rota GET /api/v1/status
- [ ] Frontend: estados visuais (loading, error, success, idle)
- [ ] OperationMapper + log de todas operações
- [ ] Validação robusta de inputs (device, user)
- [ ] Docs: INSTALL.md, CONTRIBUTING.md
- [ ] **Release v0.1.0 — MVP funcional**

### Sprint 4 — Ingest Capability (2 semanas)

- [ ] IngestPipelineInterface + IngestPipeline
- [ ] IngestStepInterface + 3 steps (Scan, Dedup, Classify)
- [ ] IngestJob (Background Job do Nextcloud)
- [ ] IngestController + rota POST /api/v1/ingest
- [ ] Listener: TriggerScanOnMount (opcional, configurável)
- [ ] Listener: NotifyOnIngestComplete
- [ ] Frontend: pipeline config + progress bar
- [ ] **Release v0.2.0 — Integrações**

### Sprint 5 — Daemon + Bridge (2 semanas)

- [ ] devnull-daemon: scaffolding Python + FastAPI
- [ ] daemon: DetectionPoller + UdevMonitor
- [ ] daemon: REST API (/disks, /mount, /health)
- [ ] App: DaemonBridgeDetector (chama daemon em vez de lsblk local)
- [ ] App: HttpDaemonClient + NullDaemonClient (fallback)
- [ ] systemd unit + install script
- [ ] **Release v0.3.0 — Hybrid Architecture**

### Sprint 6 — Automação (2 semanas)

- [ ] Pipelines configuráveis por user (DB)
- [ ] Auto-trigger on mount
- [ ] Auto-mount by serial/label rules
- [ ] SMART health check (SmartctlStep)
- [ ] Dashboard de métricas
- [ ] **Release v0.4.0 — Full Automation**

**Estimativa total:** 10-12 semanas part-time até v0.4.0

---

## 11. Estrutura do Projeto (Completa)

```
DevNull/                              ← Raiz (monorepo)
├── .kiro/
│   ├── steering/
│   │   └── architecture.md           ← Regras de contribuição arquitetural
│   └── specs/
│       ├── disk-detection/
│       ├── mount-system/
│       ├── ingest-pipeline/
│       └── daemon-bridge/
├── app/                               ← Nextcloud PHP App
│   ├── appinfo/
│   │   ├── info.xml
│   │   └── routes.php
│   ├── lib/
│   │   ├── AppInfo/Application.php
│   │   ├── Capability/               ← INTERFACES (contratos)
│   │   ├── Detection/                ← Domínio: Detecção
│   │   ├── Mount/                    ← Domínio: Montagem
│   │   ├── Storage/                  ← Domínio: Registro NC
│   │   ├── Ingest/                   ← Domínio: Pipeline
│   │   ├── Bridge/                   ← Domínio: Comunicação daemon
│   │   ├── Controller/               ← API Layer
│   │   ├── Db/                       ← Repository Layer
│   │   ├── BackgroundJob/            ← Jobs async
│   │   ├── Event/                    ← Eventos
│   │   ├── Listener/                 ← Reações a eventos
│   │   └── Command/                  ← Shell interaction
│   ├── src/                           ← Frontend Vue.js
│   │   ├── main.js
│   │   ├── App.vue
│   │   ├── transport/                ← StatusTransport abstraction
│   │   └── components/
│   ├── templates/
│   ├── css/
│   ├── img/
│   ├── tests/
│   │   ├── Unit/
│   │   └── Integration/
│   ├── composer.json
│   ├── package.json
│   └── webpack.config.js
├── daemon/                            ← Python Daemon (opcional)
│   ├── devnull_daemon/
│   │   ├── api/
│   │   ├── detection/
│   │   ├── mount/
│   │   ├── workers/
│   │   └── models/
│   ├── tests/
│   ├── systemd/
│   ├── pyproject.toml
│   └── .env.example
├── docs/
│   ├── project-plan.md               ← Este documento
│   ├── architecture.md               ← ADR + diagramas
│   ├── install.md                     ← Guia de instalação
│   └── contributing.md               ← Como contribuir
├── scripts/                           ← Automação de dev/deploy
│   ├── install-polkit.sh
│   └── setup-dev.sh
├── README.md
├── CHANGELOG.md
├── LICENSE
└── .gitignore
```

### Convenções de Nomenclatura

| Tipo | Padrão | Exemplo |
|------|--------|---------|
| Pastas | kebab-case (inglês) | `disk-detection/` |
| Classes PHP | PascalCase (PSR-4) | `UdisksMountStrategy.php` |
| Interfaces PHP | PascalCase + Interface suffix | `MountStrategyInterface.php` |
| Componentes Vue | PascalCase | `DiskCard.vue` |
| Arquivos .md | kebab-case | `project-plan.md` |
| Tabelas DB | snake_case com prefixo `oc_devnull_` | `oc_devnull_disks` |
| Rotas API | kebab-case | `/api/v1/disks` |
| Python modules | snake_case | `udev_monitor.py` |

---

## 12. Event-Driven Design

Os módulos comunicam via eventos, não por chamada direta. Isso garante que
adicionar/remover funcionalidade não quebra o sistema (P2, P6).

```
DiskDetectedEvent      → Listener pode: notificar user, auto-mount
DiskMountedEvent       → Listener pode: trigger pipeline, registrar storage
DiskUnmountedEvent     → Listener pode: remover storage, limpar logs
IngestStartedEvent     → Listener pode: atualizar status, notificar
IngestCompletedEvent   → Listener pode: notificar, gerar relatório
IngestFailedEvent      → Listener pode: notificar erro, retry
```

**Exemplo de desacoplamento:**

Hoje: montar um disco notifica o user.
Amanhã: montar um disco inicia pipeline + notifica + gera relatório.
Mudança: ZERO alteração no MountService. Apenas novos Listeners registrados.

---

## 13. Fallback Gracioso (P3: Incremental Evolution)

O sistema funciona em 3 modos, sem recompilação:

| Modo | Daemon | Capabilities disponíveis | UX |
|------|--------|--------------------------|-----|
| **Standalone** | Offline | Detecção manual (refresh), mount local, ingest via cron | Básico, funcional |
| **Enhanced** | Online | Auto-detect, mount via daemon, workers paralelos | Completo |
| **Degraded** | Crash/timeout | Fallback automático para standalone | Transparente |

O `DaemonBridgeDetector` tenta o daemon primeiro. Se falha (timeout 2s),
delega para `LsblkDetector` silenciosamente. O user não percebe.

---

## 14. Apêndices

### A. Polkit Rule para udisks2

```javascript
// /etc/polkit-1/rules.d/99-devnull-udisks2.rules
polkit.addRule(function(action, subject) {
    if ((action.id == "org.freedesktop.udisks2.filesystem-mount" ||
         action.id == "org.freedesktop.udisks2.filesystem-unmount-others") &&
        subject.user == "www-data") {
        return polkit.Result.YES;
    }
});
```

### B. Contrato do ficheiro `.devnull`

Criado no root de cada mountpoint gerido pela app:

```json
{
    "managed_by": "devnull",
    "version": "1.0",
    "mounted_at": "2026-08-06T23:30:00Z",
    "mounted_by": "mulder",
    "device": "sdb1",
    "serial": "WD-ABC123"
}
```

### C. Referência: lsblk output

```bash
lsblk --json --output NAME,SIZE,FSTYPE,LABEL,MOUNTPOINT,TYPE,SERIAL,MODEL
```

```json
{
  "blockdevices": [
    {
      "name": "sdb1",
      "size": "931.5G",
      "fstype": "ntfs",
      "label": "Backup2020",
      "mountpoint": null,
      "type": "part",
      "serial": "WD-ABC123",
      "model": "WD Elements"
    }
  ]
}
```

### D. Diagrama de sequência: Mount com daemon

```
User → Frontend → MountController → DaemonBridgeDetector
                                          │
                                          ├─ try: HttpDaemonClient.mount()
                                          │       → daemon monta + retorna mountpoint
                                          │
                                          └─ catch timeout:
                                                → UdisksMountStrategy.mount() (local)
                                                → log warning "daemon offline"
```

---

*Documento criado: Agosto 2026*
*Última atualização: Agosto 2026*
*Versão: 2.0.0*
*Status: Aprovado — decisões consolidadas, pronto para implementação*
