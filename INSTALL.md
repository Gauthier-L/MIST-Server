# Installation du Plugin MulticastSnapin

## Prérequis
- FOG Server installé (n'importe quelle version)
- Accès root au serveur FOG
- udpcast installé (`apt-get install udpcast` ou `yum install udpcast`)

## Installation Rapide

### 1. Télécharger le plugin

```bash
# Cloner depuis la branche plugin-only
git clone -b claude/multicastsnapin-plugin-K7ijD https://github.com/Gauthier-L/MIST-Server.git multicastsnapin-plugin
cd multicastsnapin-plugin
```

### 2. Copier les fichiers dans FOG

```bash
# Copier le plugin
sudo cp -r packages/web/lib/plugins/multicastsnapin /var/www/html/fog/lib/plugins/

# Copier le service daemon
sudo mkdir -p /opt/fog/service/FOGMulticastSnapinManager
sudo cp packages/service/FOGMulticastSnapinManager/FOGMulticastSnapinManager /opt/fog/service/FOGMulticastSnapinManager/
sudo chmod +x /opt/fog/service/FOGMulticastSnapinManager/FOGMulticastSnapinManager

# Ajuster les permissions
sudo chown -R www-data:www-data /var/www/html/fog/lib/plugins/multicastsnapin
```

### 3. Activer le plugin via l'interface Web FOG

1. Connectez-vous à l'interface Web FOG
2. Allez dans **Plugin Management**
3. Trouvez **Multicast Snapin**
4. Cliquez sur **Install**
5. Cliquez sur **Activate**

Le plugin va automatiquement :
- ✅ Créer les tables de base de données
- ✅ Installer et démarrer le service systemd
- ✅ Activer le menu dans l'interface FOG

### 4. Vérifier l'installation

```bash
# Vérifier que le service tourne
sudo systemctl status FOGMulticastSnapinManager

# Voir les logs
sudo journalctl -u FOGMulticastSnapinManager -f
```

## Utilisation

1. Créez un groupe avec au moins 3 machines
2. Allez dans **Multicast Snapin → Create New Session**
3. Sélectionnez le snapin et le groupe
4. Le reste est automatique !

## Désinstallation

```bash
# Arrêter et désactiver le service
sudo systemctl stop FOGMulticastSnapinManager
sudo systemctl disable FOGMulticastSnapinManager
sudo rm /lib/systemd/system/FOGMulticastSnapinManager.service
sudo systemctl daemon-reload

# Supprimer les fichiers
sudo rm -rf /var/www/html/fog/lib/plugins/multicastsnapin
sudo rm -rf /opt/fog/service/FOGMulticastSnapinManager
```

Puis désactiver le plugin via l'interface Web FOG.

## Support

- **Documentation** : Voir `packages/web/lib/plugins/multicastsnapin/README.md`
- **Logs** : `journalctl -u FOGMulticastSnapinManager -f`
- **Issues** : GitHub Issues
