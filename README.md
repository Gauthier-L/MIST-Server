# FOG MulticastSnapin Plugin

Plugin pour FOG permettant le déploiement multicast de snapins vers plusieurs machines simultanément.

## Description

Ce plugin optimise la bande passante réseau lors du déploiement de snapins sur de grands parcs informatiques en utilisant le multicast UDP (udpcast) au lieu de téléchargements FTP individuels.

## Contenu du Plugin

```
packages/
├── service/
│   └── FOGMulticastSnapinManager/
│       └── FOGMulticastSnapinManager        # Service daemon
│
└── web/lib/
    ├── service/                              # Services core
    │   ├── multicastsnapinmanager.class.php  # Gestionnaire principal
    │   └── multicastsnapintask.class.php     # Gestion des tâches
    │
    └── plugins/multicastsnapin/              # Plugin
        ├── class/                             # Classes modèles
        ├── hooks/                             # Hooks d'intégration
        ├── pages/                             # Interface web
        ├── systemd/                           # Unit systemd
        ├── config/                            # Configuration
        └── README.md                          # Documentation complète
```

## Installation

### Prérequis

- FOG Server fonctionnel
- udpcast installé (\`/usr/local/sbin/udp-sender\`)
- PHP 7.0+
- Accès root pour l'installation du service systemd

### Installation Automatique (Recommandée)

1. **Copier les fichiers du plugin** dans votre installation FOG :
   \`\`\`bash
   # Se placer dans le dépôt FOG-Server
   cd /path/to/FOG-Server

   # Copier les fichiers du plugin (préserver l'arborescence)
   cp -r /path/to/multicastsnapin-plugin/packages/* packages/
   \`\`\`

2. **Activer le plugin** via l'interface web FOG :
   - Aller dans **Plugin Management**
   - Trouver **Multicast Snapin**
   - Cliquer sur **Activate**

3. **Le service s'installe automatiquement** :
   - ✅ Création de la table \`multicastSnapinSessions\`
   - ✅ Copie de l'exécutable du service
   - ✅ Installation du fichier systemd
   - ✅ Activation et démarrage automatique

4. **Vérifier le service** :
   \`\`\`bash
   sudo systemctl status FOGMulticastSnapinManager
   sudo journalctl -u FOGMulticastSnapinManager -f
   \`\`\`

### Installation Manuelle (Si l'automatique échoue)

\`\`\`bash
# Copier service executable
sudo mkdir -p /opt/fog/service/FOGMulticastSnapinManager
sudo cp packages/service/FOGMulticastSnapinManager/FOGMulticastSnapinManager \\
    /opt/fog/service/FOGMulticastSnapinManager/
sudo chmod +x /opt/fog/service/FOGMulticastSnapinManager/FOGMulticastSnapinManager

# Installer unit systemd
sudo cp packages/web/lib/plugins/multicastsnapin/systemd/FOGMulticastSnapinManager.service \\
    /lib/systemd/system/

# Activer et démarrer
sudo systemctl daemon-reload
sudo systemctl enable FOGMulticastSnapinManager
sudo systemctl start FOGMulticastSnapinManager
\`\`\`

## Utilisation Rapide

### Créer une Session Multicast

1. Créer un groupe avec au moins 3 machines
2. Aller dans **Multicast Snapin → Create New Session**
3. Sélectionner :
   - **Snapin** : Le snapin à déployer
   - **Host Group** : Le groupe cible (≥ 3 machines)
   - **Storage Group** : Source de stockage
4. Cliquer sur **Create Multicast Session**

**Paramètres automatiques** :
- ✅ Nom : \`{Snapin} - {Group}\`
- ✅ Port : Alloué automatiquement (évite collisions)
- ✅ Nombre de clients : Calculé d'après le groupe
- ✅ Interface réseau : Détectée depuis configuration

### Monitoring

- **Active Sessions** : Sessions en cours
- **Logs service** : \`journalctl -u FOGMulticastSnapinManager -f\`
- **Logs session** : \`/opt/fog/log/multicast-snapin-{ID}.log\`

## Fonctionnalités

- ✅ Déploiement multicast (≥ 3 machines)
- ✅ Allocation automatique des ports (évite collisions)
- ✅ Service robuste (pattern FOGMulticastManager)
- ✅ Gestion anti-zombies des processus
- ✅ Installation automatique
- ✅ Interface simplifiée (0 configuration manuelle)
- ✅ Assignation par groupes
- ✅ Compatible avec multicast images

## Documentation Complète

Voir [\`packages/web/lib/plugins/multicastsnapin/README.md\`](packages/web/lib/plugins/multicastsnapin/README.md) pour :
- Architecture détaillée
- Configuration avancée
- Dépannage
- Patterns techniques

## Support

- **Logs** : \`journalctl -u FOGMulticastSnapinManager -f\`
- **Documentation FOG** : https://fogproject.org
- **Issues** : Ouvrir une issue sur GitHub

## Licence

GPL v3

## Auteurs

Gauthier-L, University of Lille, Campus-Gare RBX - Plugin développé pour optimiser les déploiements de snapins sur grands parcs.

---

**Version** : 1.0.0
**Testé avec** : FOG Server
**Dépendances** : udpcast, systemd, PHP 7.0+
