# Multicast Snapin Plugin for FOG

## Description

Le plugin **MulticastSnapin** permet de déployer des snapins en mode multicast vers plusieurs machines simultanément, optimisant ainsi l'utilisation de la bande passante réseau sur de grands parcs informatiques.

Au lieu que chaque client télécharge individuellement le snapin via FTP (unicast), tous les clients rejoignent une session multicast et reçoivent le fichier simultanément via UDP multicast (utilisant udpcast).

## Fonctionnalités

- ✅ Déploiement multicast de snapins vers plusieurs hosts (≥ 3 machines)
- ✅ **Allocation automatique des ports** (évite collisions avec multicast images)
- ✅ **Service robuste** basé sur FOGMulticastManager (patterns éprouvés)
- ✅ **Gestion anti-zombies** des processus (killAll récursif)
- ✅ Interface web simplifiée de gestion des sessions
- ✅ Assignation automatique par groupes de hosts
- ✅ Génération automatique des noms de session
- ✅ Configuration automatique de l'interface réseau
- ✅ **Installation automatique** du service lors de l'activation du plugin
- ✅ Scripts wrapper automatiques pour clients Windows et Linux
- ✅ Compatible avec l'infrastructure multicast existante de FOG
- ✅ Cohabitation avec les déploiements snapin unicast traditionnels
- ✅ Monitoring en temps réel des sessions actives

## Architecture

### Composants

1. **Service Core** (dans `/packages/web/lib/service/`)
   - `MulticastSnapinManager` : Service daemon (même pattern que FOGMulticastManager)
   - `MulticastSnapinTask` : Gestion des tâches multicast individuelles

2. **Classes Modèles** (dans `/packages/web/lib/plugins/multicastsnapin/class/`)
   - `MulticastSnapinSession` : Représente une session multicast
   - `MulticastSnapinSessionManager` : Gestion CRUD et installation automatique
   - `MulticastSnapinWrapper` : Génération des scripts wrapper clients

3. **Interface Web**
   - Page de gestion des sessions multicast
   - Hooks d'intégration (menu, API)

4. **Client-side**
   - Scripts wrapper générés automatiquement (PowerShell/Bash)
   - Installation automatique de udp-receiver
   - Réception du snapin via multicast
   - Exécution et reporting

### Flux de Fonctionnement

```
1. Admin crée session multicast (Web UI)
   ├─> Sélectionne snapin
   ├─> Sélectionne groupe de hosts (≥ 3 machines)
   ├─> Nom généré automatiquement : "{Snapin} - {Group}"
   ├─> Port alloué automatiquement (évite collisions)
   ├─> Nombre de clients calculé automatiquement
   └─> Session créée avec état "Queued"

2. Service FOGMulticastSnapinManager (daemon)
   ├─> Boucle toutes les 10s (scan sessions Queued/Progress)
   ├─> Valide: fichier snapin, port pair, >= 3 clients
   ├─> Lance udp-sender via proc_open()
   ├─> Stocke procRef pour monitoring
   └─> Marque session "In Progress"

3. Monitoring continu
   ├─> Vérifie proc_get_status($procRef)
   ├─> Si running: updateStats() (pourcentage)
   ├─> Si terminé ou timeout: complete() ou cancel()
   └─> killTask() avec killAll() récursif (anti-zombies)

4. Clients FOG (tous les hosts du groupe)
   ├─> Téléchargent wrapper script via FTP (léger)
   ├─> Installent udp-receiver si nécessaire
   ├─> Rejoignent session multicast
   ├─> Reçoivent snapin en parallèle
   ├─> Exécutent le snapin
   └─> Reportent résultats au serveur

5. Fin de session
   ├─> Tous clients ont reçu le snapin OU timeout
   ├─> Service arrête udp-sender (SIGTERM + killAll)
   ├─> Session marquée "Complete" (stateID=4)
   └─> Nettoyage des logs et processus
```

## Installation

### Prérequis

- FOG Server (FOG fork) fonctionnel
- udpcast installé (`/usr/local/sbin/udp-sender`)
- PHP 7.0+
- **Accès root** pour l'installation automatique du service systemd

### Installation Plug&Play

1. **Le plugin est déjà dans l'arborescence FOG** (`packages/web/lib/plugins/multicastsnapin/`)

2. **Activer le plugin via l'interface web FOG** :
   - Aller dans **Plugin Management**
   - Trouver **Multicast Snapin**
   - Cliquer sur **Activate**

3. **Le service s'installe automatiquement** :
   - ✅ Création de la table `multicastSnapinSessions`
   - ✅ Copie de l'exécutable du service dans `/opt/fog/service/FOGMulticastSnapinManager/`
   - ✅ Installation du fichier systemd dans `/lib/systemd/system/`
   - ✅ Activation et démarrage automatique via `systemctl`

4. **Vérifier le service** :
   ```bash
   sudo systemctl status FOGMulticastSnapinManager
   sudo journalctl -u FOGMulticastSnapinManager -f
   ```

> **Note** : L'installation automatique nécessite que le serveur web tourne en tant que root ou avec `sudo`. Si l'installation échoue, vérifiez les logs : `tail -f /var/log/apache2/error.log` ou `/var/log/httpd/error_log`.

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
     - **Host Group** : Sélectionner le groupe (seuls les groupes avec ≥ 3 machines sont affichés)
     - **Storage Group** : Groupe de stockage source
   - Cliquer sur **Create Multicast Session**

3. **Paramètres générés automatiquement** :
   - **Nom de session** : `{Snapin} - {Group}`
   - **Nombre de clients** : Calculé d'après le groupe
   - **Port UDP** : Alloué automatiquement (évite collisions avec multicast images)
   - **Interface réseau** : Utilise l'interface du storage node ou `FOG_MULTICAST_INTERFACE`

**Notes importantes** :
- ⚠️ Le multicast nécessite **au moins 3 machines** (≥ 3 hosts)
- Les groupes avec moins de 3 hosts ne sont pas disponibles dans la liste
- Tous les hosts du groupe recevront le snapin automatiquement
- Le port est alloué automatiquement sur la plage `FOG_UDPCAST_STARTINGPORT` à 65534
- Aucune collision possible entre sessions snapin et sessions images multicast

### Monitoring

- **Active Sessions** : Voir les sessions en cours
- **Session Details** : Voir progression, clients connectés, etc.
- **Cancel Session** : Annuler une session en cours si nécessaire
- **Logs** : `journalctl -u FOGMulticastSnapinManager -f`
- **Logs par session** : `/opt/fog/log/multicast-snapin-{ID}.log`

## Configuration

### Paramètres FOG (globalSettings)

Les paramètres multicast existants sont réutilisés :

- `FOG_MULTICAST_ADDRESS` : Adresse multicast (défaut: 224.0.0.1)
- `FOG_MULTICAST_INTERFACE` : Interface réseau par défaut (défaut: eth0)
- `FOG_MULTICAST_DUPLEX` : Mode duplex
- `FOG_UDPCAST_STARTINGPORT` : Port de départ (défaut: 63100)
- `FOG_UDPCAST_MAXWAIT` : Timeout maximum en minutes (défaut: 10)
- `FOG_MULTICAST_MAX_SESSIONS` : Nombre max de sessions simultanées (défaut: 5)
- `MULTICASTSLEEPTIME` : Intervalle de vérification du service en secondes (défaut: 10)
- `MULTICASTGLOBALENABLED` : Activer/désactiver multicast globalement

**Note** : L'interface réseau utilisée pour chaque session est déterminée automatiquement :
1. Si le storage node a une interface définie, elle est utilisée en priorité
2. Sinon, utilise `FOG_MULTICAST_INTERFACE`
3. Par défaut : `eth0`

### Allocation Automatique des Ports

Le système alloue automatiquement les ports pour éviter les collisions :

1. **Récupération des ports utilisés** :
   - Sessions snapin multicast actives
   - Sessions images multicast actives

2. **Algorithme d'allocation** :
   - Démarre à `FOG_UDPCAST_STARTINGPORT` (défaut 63100)
   - Incrémente par 2 (ports pairs uniquement)
   - Évite tous les ports déjà utilisés
   - Wrap-around à 24576 si > 65534

3. **Calcul de l'adresse multicast** (même que images) :
   ```php
   $address = base_address + ((port / 2 + 1) % max_sessions)
   ```

## Tables de Base de Données

### multicastSnapinSessions

Stocke les sessions multicast :

| Champ | Type | Description |
|-------|------|-------------|
| mssID | INT | ID unique |
| mssName | VARCHAR(250) | Nom de la session (généré auto) |
| mssBasePort | INT | Port UDP (pair, alloué auto) |
| mssSnapinID | INT | ID du snapin |
| mssGroupID | INT | ID du groupe de hosts |
| mssClients | INT | Nombre de clients attendus (calculé auto) |
| mssSessClients | INT | Nombre de clients connectés |
| mssInterface | VARCHAR(15) | Interface réseau |
| mssState | INT | État (0=Queued, 1=Checked In, 3=InProgress, 4=Complete, 5=Cancelled) |
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

# Redémarrer le service
sudo systemctl restart FOGMulticastSnapinManager
```

### Les sessions restent en "Queued"

- Vérifier que le service tourne : `systemctl status FOGMulticastSnapinManager`
- Vérifier que le storage node est master du groupe
- Vérifier les logs : `/opt/fog/log/multicast-snapin-*.log`
- Vérifier que `MULTICASTGLOBALENABLED` est activé
- Vérifier que le fichier snapin existe sur le storage node

### Les clients ne reçoivent pas le snapin

- Vérifier que udp-receiver est installé sur les clients
- Vérifier la connectivité réseau multicast
- Vérifier que le port n'est pas bloqué par un firewall
- Vérifier les logs wrapper sur le client : `%TEMP%\fog-multicast-snapin.log` (Windows) ou `/tmp/fog-multicast-snapin.log` (Linux)

### Processus zombies

**Ce problème ne devrait JAMAIS se produire** grâce à `killAll()` récursif :
```php
// Le service tue tous les processus enfants récursivement
function killAll($pid, $sig) {
    exec("ps -ef|awk '\$3 == '$pid' {print \$2}'", $output);
    foreach ($output as $childPid) {
        killAll($childPid, $sig);  // Récursif
    }
    posix_kill($pid, $sig);
}
```

Si des zombies apparaissent quand même :
```bash
# Identifier les zombies
ps aux | grep 'Z'

# Redémarrer le service
sudo systemctl restart FOGMulticastSnapinManager
```

## Architecture Technique

### Pattern FOGMulticastManager

Le service suit exactement le même pattern que `FOGMulticastManager` :

```php
while (true) {
    // 1. Sleep timer avec usleep(100000)
    // 2. waitDbReady()
    // 3. Récupérer tâches (Queued/Progress)
    // 4. Pour chaque tâche:
    foreach ($tasks as $task) {
        if (!$existing) {
            // Valider et lancer
            $task->startTask();  // proc_open()
            $KnownTasks[] = $task;
        } else {
            // Monitorer
            if ($task->isRunning($procRef)) {
                $task->updateStats();
            } else {
                $task->complete() or cancel();
                $task->killTask();  // killAll() récursif
            }
        }
    }
}
```

### Gestion des Processus

```php
// Lancement avec descripteurs
$descriptor = [
    0 => ['pipe', 'r'],                    // stdin
    1 => ['file', $logfile, 'a'],          // stdout
    2 => ['file', $servicelog, 'a']        // stderr
];
$procRef = proc_open($cmd, $descriptor, $pipes);

// Monitoring
$status = proc_get_status($procRef);
$isRunning = $status['running'];
$pid = $status['pid'];

// Arrêt propre (anti-zombies)
killAll($pid, SIGTERM);  // Tue tous les enfants récursivement
proc_terminate($procRef, SIGTERM);
proc_close($procRef);
```

## Évolutions Futures

- [x] ~~Interface web pour assigner hosts aux sessions~~ → Implémenté via sélection de groupe
- [x] ~~Génération automatique du nom de session~~ → Implémenté
- [x] ~~Validation automatique du nombre minimum de machines~~ → Implémenté (≥ 3)
- [x] ~~Allocation automatique des ports~~ → Implémenté avec évitement collisions
- [x] ~~Service robuste anti-zombies~~ → Implémenté (pattern FOGMulticastManager)
- [x] ~~Installation automatique~~ → Implémenté (Option 1)
- [ ] Déclenchement automatique quand N hosts d'un groupe ont le même snapin en attente
- [ ] Support de multiples snapins par session
- [ ] Dashboard de statistiques et historique
- [ ] Support de la réplication multicast entre storage nodes
- [ ] Intégration avec le planificateur de tâches FOG
- [ ] Notification des hosts pour rejoindre automatiquement la session multicast

## Licence

GPL v3

## Auteurs

Gauthier-L, University of Lille, Campus-Gare RBX - Plugin développé pour optimiser les déploiements de snapins sur grands parcs.

## Support

Pour toute question ou problème :
- Consulter les logs : `journalctl -u FOGMulticastSnapinManager -f`
- Vérifier la documentation FOG sur le multicast
- Ouvrir une issue sur le dépôt FOG
