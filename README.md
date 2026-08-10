# /dev/null

> Where your data goes to live.

App Nextcloud + daemon para auto-ingest de discos externos.
Detecta, monta, escaneia, deduplica e classifica — tudo pela interface.

## Arquitetura

```
DevNull/
├── app/        ← Nextcloud PHP app (UI, API, DB, integrações)
├── daemon/     ← Python daemon (hardware detection, mount, workers) — opcional
└── docs/       ← Documentação do projeto
```

A app PHP funciona standalone. O daemon é um enhancer opcional para
real-time hotplug detection e workers paralelos.

## Status

**Em desenvolvimento** — Fase: Scaffolding (Sprint 0)

## O que faz

1. Detecta HDs/USBs conectados ao servidor
2. Lista discos disponíveis na interface do Nextcloud
3. Com um clique: monta e registra como external storage
4. Pipeline configurável: scan, deduplicação, classificação AI
5. Multi-tenant: admin monta, users veem o que é compartilhado
6. Desmonta/ejeta quando desejado

## Requisitos

**Mínimo (app PHP standalone):**
- Nextcloud 28+
- PHP 8.2+
- Debian 12+ / Ubuntu 22.04+
- `udisks2`, `util-linux`
- Filesystems: `ntfs-3g`, `exfatprogs`, `hfsprogs`

**Completo (com daemon):**
- Python 3.11+
- `pyudev` (para hotplug real-time)

## Instalação

```bash
# App Nextcloud
cd /var/www/nextcloud/apps
git clone https://github.com/luiscarmo/devnull.git
ln -s devnull/app devnull-app
sudo -u www-data php /var/www/nextcloud/occ app:enable devnull

# Daemon (opcional)
cd devnull/daemon
python3 -m venv .venv && source .venv/bin/activate
pip install -e .
sudo cp systemd/devnull-daemon.service /etc/systemd/system/
sudo systemctl enable --now devnull-daemon
```

## Desenvolvimento

```bash
# PHP app
cd app && composer install && npm install && npm run dev

# Daemon
cd daemon && pip install -e ".[dev]" && pytest
```

## Documentação

- [Plano do Projeto](docs/project-plan.md)
- [Daemon README](daemon/README.md)

## Licença

AGPL-3.0
