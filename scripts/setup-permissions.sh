#!/bin/bash
# DevNull — Setup permissions for www-data to mount/unmount disks
#
# Run once on the server after installing the app.

set -e

echo "==> Configurando permissões para DevNull..."

# 1. Polkit rule for udisks2 (mount without root)
POLKIT_RULE="/etc/polkit-1/rules.d/99-devnull-udisks2.rules"
if [ ! -f "${POLKIT_RULE}" ]; then
    sudo tee "${POLKIT_RULE}" > /dev/null << 'EOF'
polkit.addRule(function(action, subject) {
    if ((action.id == "org.freedesktop.udisks2.filesystem-mount" ||
         action.id == "org.freedesktop.udisks2.filesystem-mount-other-seat" ||
         action.id == "org.freedesktop.udisks2.filesystem-unmount-others") &&
        subject.user == "www-data") {
        return polkit.Result.YES;
    }
});
EOF
    echo "   Polkit rule criada: ${POLKIT_RULE}"
else
    echo "   Polkit rule já existe"
fi

# 2. Sudoers rule for www-data to run occ and mount commands
SUDOERS_FILE="/etc/sudoers.d/devnull"
sudo tee "${SUDOERS_FILE}" > /dev/null << 'EOF'
# DevNull app permissions
www-data ALL=(ALL) NOPASSWD: /usr/bin/udisksctl mount *
www-data ALL=(ALL) NOPASSWD: /usr/bin/udisksctl unmount *
www-data ALL=(ALL) NOPASSWD: /usr/bin/mkdir -p /media/devnull/*
EOF
sudo chmod 440 "${SUDOERS_FILE}"
echo "   Sudoers rule criada: ${SUDOERS_FILE}"

# 3. Create mount base directory
MOUNT_BASE="/media/devnull"
if [ ! -d "${MOUNT_BASE}" ]; then
    sudo mkdir -p "${MOUNT_BASE}"
    sudo chown www-data:www-data "${MOUNT_BASE}"
    echo "   Diretório criado: ${MOUNT_BASE}"
else
    echo "   Diretório já existe: ${MOUNT_BASE}"
fi

# 4. Ensure www-data can execute lsblk
sudo chmod 755 /usr/bin/lsblk

echo ""
echo "==> Pronto! Permissões configuradas."
echo "   O DevNull agora pode montar/desmontar discos via interface web."
