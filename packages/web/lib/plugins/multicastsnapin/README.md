# Multicast Snapin Plugin for MIST (FOG)

## Description

Le plugin **MulticastSnapin** permet de déployer des snapins en mode multicast vers plusieurs machines simultanément, optimisant ainsi l'utilisation de la bande passante réseau sur de grands parcs informatiques.

Au lieu que chaque client télécharge individuellement le snapin via FTP (unicast), tous les clients rejoignent une session multicast et reçoivent le fichier simultanément via UDP multicast (utilisant udpcast).

## Fonctionnalités

- ✅ Déploiement multicast de snapins vers plusieurs hosts
- ✅ Interface web de gestion des sessions multicast
- ✅ Service daemon automatique (FOGMulticastSnapinManager)
- ✅ Scripts wrapper automatiques pour clients Windows et Linux
- ✅ Compatible avec l'infrastructure multicast existante de MIST
- ✅ Cohabitation avec les déploiements snapin unicast traditionnels
- ✅ Monitoring en temps réel des sessions actives

## Architecture

### Composants

1. **Classes Modèles**
   - `MulticastSnapinSession` : Représente une session multicast
   - `MulticastSnapinSessionManager` : Gestion CRUD des sessions
   - `MulticastSnapinWrapper` : Génération des scripts wrapper clients

2. **Service Daemon**
   - `FOGMulticastSnapinManager` : Service systemd qui gère les sessions
   - Lance udp-sender pour chaque session
   - Monitore les sessions actives
   - Gère les timeouts et erreurs

3. **Interface Web**
   - Page de création de sessions multicast
   - Liste des sessions actives/complétées
   - Monitoring en temps réel
   - Annulation de sessions

4. **Client-side**
   - Scripts wrapper générés automatiquement (PowerShell/Bash)
   - Installation automatique de udp-receiver
   - Réception du snapin via multicast
   - Exécution et reporting

### Flux de Fonctionnement

```
1. Admin crée session multicast (Web UI)
   ├─> Sélectionne snapin
   ├─> Sélectionne groupe de hosts (> 2 machines)
   ├─> Nom généré automatiquement : "{Snapin} - {Group}"
   ├─> Nombre de clients calculé automatiquement
   └─> Session créée avec état "Queued"

2. Service FOGMulticastSnapinManager (daemon)
   ├─> Détecte sessions en attente
   ├─> Lance udp-sender avec le fichier snapin
   └─> Marque session "In Progress"

3. Clients FOG (tous les hosts du groupe)
   ├─> Téléchargent wrapper script via FTP (léger)
   ├─> Installent udp-receiver si nécessaire
   ├─> Rejoignent session multicast
   ├─> Reçoivent snapin en parallèle
   ├─> Exécutent le snapin
   └─> Reportent résultats au serveur

4. Fin de session
   ├─> Tous clients ont reçu le snapin OU timeout
   ├─> Service arrête udp-sender
   └─> Session marquée "Complete"
```

## Installation

### Prérequis

- MIST Server (FOG fork) fonctionnel
- udpcast installé (`/usr/local/sbin/udp-sender`)
- PHP 7.0+
- Accès root pour installation du service systemd

### Étapes d'installation

1. **Le plugin est déjà dans l'arborescence MIST** (`packages/web/lib/plugins/multicastsnapin/`)

2. **Activer le plugin via l'interface web MIST** :
   ```
   Plugin Management → Multicast Snapin → Activate
   ```

3. **Installer le service systemd** :
   ```bash
   # Copier le service systemd
   sudo cp packages/web/lib/plugins/multicastsnapin/systemd/FOGMulticastSnapinManager.service \
       /lib/systemd/system/

   # Créer le répertoire de service
   sudo mkdir -p /opt/fog/service/FOGMulticastSnapinManager

   # Copier l'exécutable du service
   sudo cp packages/service/FOGMulticastSnapinManager/FOGMulticastSnapinManager \
       /opt/fog/service/FOGMulticastSnapinManager/

   # Rendre exécutable
   sudo chmod +x /opt/fog/service/FOGMulticastSnapinManager/FOGMulticastSnapinManager

   # Recharger systemd
   sudo systemctl daemon-reload

   # Activer et démarrer le service
   sudo systemctl enable FOGMulticastSnapinManager
   sudo systemctl start FOGMulticastSnapinManager

   # Vérifier le statut
   sudo systemctl status FOGMulticastSnapinManager
   ```

4. **Vérifier les logs** :
   ```bash
   sudo journalctl -u FOGMulticastSnapinManager -f
   ```

## Utilisation

### Créer une session multicast

1. **Créer un groupe de hosts** (si pas déjà fait) :
   - Aller dans **Group Management**
   - Créer un groupe contenant **au moins 3 machines**
   - Ajouter les hosts au groupe

2. **Créer la session multicast** :
   - Aller dans **Multicast Snapin → Create New Session**
   - Remplir le formulaire :
     - **Snapin** : Sélectionner le snapin à déployer
     - **Host Group** : Sélectionner le groupe (seuls les groupes avec > 2 machines sont affichés)
     - **Storage Group** : Groupe de stockage source
     - **Base Port** : Port UDP (doit être pair, ex: 63100)
     - **Network Interface** : Interface réseau (ex: eth0)
   - Cliquer sur **Create Multicast Session**

3. **Le nom de la session est généré automatiquement** : `{Snapin} - {Group}`

**Notes importantes** :
- ⚠️ Le multicast nécessite **au moins 3 machines** pour être efficace
- Les groupes avec moins de 3 hosts ne sont pas disponibles dans la liste
- Tous les hosts du groupe recevront le snapin automatiquement
- Le nombre de clients est calculé automatiquement d'après le groupe

### Monitoring

- **Active Sessions** : Voir les sessions en cours
- **Session Details** : Voir progression, clients connectés, etc.
- **Cancel Session** : Annuler une session en cours si nécessaire

## Configuration

### Paramètres MIST (globalSettings)

Les paramètres multicast existants sont réutilisés :

- `FOG_MULTICAST_ADDRESS` : Adresse multicast (défaut: 224.0.0.1)
- `FOG_MULTICAST_DUPLEX` : Mode duplex
- `FOG_UDPCAST_STARTINGPORT` : Port de départ (défaut: 63100)
- `FOG_UDPCAST_MAXWAIT` : Timeout maximum en minutes (défaut: 10)
- `FOG_MULTICAST_MAX_SESSIONS` : Nombre max de sessions simultanées (défaut: 5)
- `MULTICASTSLEEPTIME` : Intervalle de vérification du service en secondes (défaut: 10)

## Tables de Base de Données

### multicastSnapinSessions

Stocke les sessions multicast :

| Champ | Type | Description |
|-------|------|-------------|
| mssID | INT | ID unique |
| mssName | VARCHAR(250) | Nom de la session (généré auto) |
| mssBasePort | INT | Port UDP (pair) |
| mssSnapinID | INT | ID du snapin |
| mssGroupID | INT | ID du groupe de hosts |
| mssClients | INT | Nombre de clients attendus (calculé auto) |
| mssSessClients | INT | Nombre de clients connectés |
| mssInterface | VARCHAR(15) | Interface réseau |
| mssState | INT | État (0=Queued, 1=InProgress, 2=Complete, 3=Cancelled) |
| mssStartDateTime | TIMESTAMP | Date/heure de démarrage |
| mssCompleteDateTime | TIMESTAMP | Date/heure de fin |
| mssStorageGroupID | INT | ID du groupe de stockage |
| mssPercent | INT | Progression (0-100) |

**Note** : Les hosts destinataires sont automatiquement déterminés via `mssGroupID` en interrogeant la table `groupAssociation`.

## Dépannage

### Le service ne démarre pas

```bash
# Vérifier les logs
sudo journalctl -u FOGMulticastSnapinManager -n 50

# Vérifier que udp-sender existe
ls -l /usr/local/sbin/udp-sender

# Vérifier les permissions
sudo chmod +x /opt/fog/service/FOGMulticastSnapinManager/FOGMulticastSnapinManager
```

### Les sessions restent en "Queued"

- Vérifier que le service tourne : `systemctl status FOGMulticastSnapinManager`
- Vérifier que le storage node est master du groupe
- Vérifier les logs : `/opt/fog/log/multicast-snapin-*.log`

### Les clients ne reçoivent pas le snapin

- Vérifier que udp-receiver est installé sur les clients
- Vérifier la connectivité réseau multicast
- Vérifier que le port n'est pas bloqué par un firewall
- Vérifier les logs wrapper sur le client : `%TEMP%\fog-multicast-snapin.log` (Windows) ou `/tmp/fog-multicast-snapin.log` (Linux)

## Évolutions Futures

- [x] ~~Interface web pour assigner hosts aux sessions~~ → Implémenté via sélection de groupe
- [x] ~~Génération automatique du nom de session~~ → Implémenté
- [x] ~~Validation automatique du nombre minimum de machines~~ → Implémenté (> 2)
- [ ] Déclenchement automatique quand N hosts d'un groupe ont le même snapin en attente
- [ ] Support de multiples snapins par session
- [ ] Dashboard de statistiques et historique
- [ ] Support de la réplication multicast entre storage nodes
- [ ] Intégration avec le planificateur de tâches FOG
- [ ] Notification des hosts pour rejoindre automatiquement la session multicast

## Licence

GPL v3

## Auteurs

MIST Team - Plugin développé pour optimiser les déploiements de snapins sur grands parcs.
