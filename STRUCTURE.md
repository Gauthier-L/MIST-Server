# Structure du Plugin MulticastSnapin

```
multicastsnapin-plugin/
├── INSTALL.md                          # Guide d'installation
├── README.md                           # Documentation principale
│
├── packages/
│   ├── service/
│   │   └── FOGMulticastSnapinManager/
│   │       └── FOGMulticastSnapinManager     # Service daemon (copié vers /opt/fog/service/)
│   │
│   └── web/lib/plugins/
│       └── multicastsnapin/                  # Plugin FOG (copié vers /var/www/html/fog/lib/plugins/)
│           │
│           ├── class/                        # Classes du plugin (auto-chargées par FOG)
│           │   ├── multicastsnapinmanager.class.php        # Gestionnaire de service multicast
│           │   ├── multicastsnapintask.class.php           # Gestion des tâches multicast
│           │   ├── multicastsnapinsession.class.php        # Modèle de session
│           │   ├── multicastsnapinsessionmanager.class.php # Manager de sessions
│           │   ├── multicastsnapinsessionassociation.class.php
│           │   ├── multicastsnapinsessionassociationmanager.class.php
│           │   ├── multicastsnapinwrapper.class.php        # Générateur de wrappers
│           │   ├── multicastsnapinwrapperentry.class.php   # Modèle wrapper entry
│           │   ├── multicastsnapinwrapperentrymanager.class.php
│           │   └── virtualstoragenode.class.php            # Noeud de stockage virtuel
│           │
│           ├── config/
│           │   └── plugin.config.php         # Configuration du plugin
│           │
│           ├── hooks/                        # Hooks d'intégration FOG
│           │   ├── addmulticastsnapinapi.hook.php          # Enregistrement API
│           │   ├── addmulticastsnapinmenuitem.hook.php     # Menu FOG
│           │   └── multicastsnapininterceptor.hook.php     # Interception SNAPIN_NODE
│           │
│           ├── pages/
│           │   └── multicastsnapinmanagementpage.class.php # Interface Web
│           │
│           ├── service/
│           │   └── wrapper.php               # Endpoint pour téléchargement wrappers
│           │
│           ├── systemd/
│           │   └── FOGMulticastSnapinManager.service       # Unit systemd
│           │
│           └── README.md                     # Documentation technique du plugin
```

## Auto-chargement FOG

FOG charge automatiquement les fichiers selon leur emplacement et extension :

| Dossier | Pattern | Chargement |
|---------|---------|------------|
| `class/` | `*.class.php` | Auto-chargé par `FOGCore::getClass()` |
| `hooks/` | `*.hook.php` | Auto-exécuté au démarrage |
| `pages/` | `*page.class.php` | Auto-chargé pour les pages Web |
| `config/` | `plugin.config.php` | Lu par le gestionnaire de plugins |

## Fichiers critiques

### Installation automatique
- `class/multicastsnapinsessionmanager.class.php::install()` - Crée tables + installe service
- `systemd/FOGMulticastSnapinManager.service` - Unit systemd
- `packages/service/FOGMulticastSnapinManager/FOGMulticastSnapinManager` - Daemon

### Intégration FOG
- `hooks/multicastsnapininterceptor.hook.php` - Intercepte téléchargements snapin
- `hooks/addmulticastsnapinmenuitem.hook.php` - Ajoute menu dans FOG
- `hooks/addmulticastsnapinapi.hook.php` - Enregistre classes dans API

### Workflow client
1. `pages/multicastsnapinmanagementpage.class.php` - Admin crée session
2. `class/multicastsnapinwrapper.class.php` - Génère scripts wrapper
3. `hooks/multicastsnapininterceptor.hook.php` - Redirige client vers wrapper
4. `service/wrapper.php` - Sert le script wrapper au client
5. `class/multicastsnapinmanager.class.php` - Service udp-sender

## Aucune modification FOG core requise

Le plugin est 100% autonome et fonctionne sur toute installation FOG.
