# Plugin qBittorrent pour Jeedom

Supervision et suivi de vos instances **qBittorrent** (via leur WebUI) dans Jeedom : état du client, vitesses de transfert, espace disque, ratio, et détail des torrents et de leurs fichiers.

> ⚠️ Plugin non officiel, développé indépendamment de qBittorrent.

## Fonctionnalités

- **Gestion multi-clients** : un équipement Jeedom par instance qBittorrent (URL de la WebUI, identifiant, mot de passe).
- **Rafraîchissement automatique** (fonction `cron()` du plugin, appelée chaque minute par Jeedom) des informations globales de chaque client :
  - État de connexion, espace disque disponible.
  - Volumes total et session (download/upload), vitesses instantanées et limites de vitesse configurées.
  - Ratio global de partage, état de la file d'attente et de la limite de vitesse alternative.
- **Détail par torrent** : sélection d'un torrent dans la liste pour afficher sa progression, sa vitesse, ses seeds/leechs, son ratio, sa taille, sa date d'ajout, son temps de partage...
- **Détail par fichier** : pour un torrent sélectionné, sélection d'un fichier de son contenu pour voir sa taille, sa progression, sa priorité et sa disponibilité.
- **Widget dashboard dédié** avec pop-up de détail torrent/fichier, et déclenchement manuel d'un rafraîchissement.
- Commande **Rafraichir** exécutable à la demande sur chaque équipement.

## Architecture

Le plugin s'appuie sur un **démon Node.js** local qui fait le pont entre Jeedom et l'API WebUI de chaque instance qBittorrent (authentification, appels REST) :

| Fichier | Rôle |
|---|---|
| [core/class/qbittorrent.class.php](core/class/qbittorrent.class.php) | `eqLogic` du plugin : cycle de vie du démon, synchronisation des clients, rafraîchissement (`cron()` → `pull()`), création des commandes, rendu du widget dashboard. |
| [core/php/qbittorrent.inc.php](core/php/qbittorrent.inc.php) | Fonction `callQbittorrent()` : appels HTTP vers le démon local. |
| [resources/qbittorrent/index.js](resources/qbittorrent/index.js) | Démon Node.js (Express) exposant une API REST interne, authentifiée par la clé API Jeedom du plugin. |
| [resources/qbittorrent/lib/ClientManager.js](resources/qbittorrent/lib/ClientManager.js) | Gestion des connexions vers les différentes instances qBittorrent (WebUI). |
| [resources/qbittorrent/lib/Client.js](resources/qbittorrent/lib/Client.js) | Client HTTP vers l'API WebUI qBittorrent (login, `syncMain`, liste des torrents, contenu d'un torrent...). |
| [core/ajax/qbittorrent.ajax.php](core/ajax/qbittorrent.ajax.php) | Point d'entrée AJAX (resynchronisation manuelle de la liste des clients). |
| [core/template/dashboard/](core/template/dashboard/) / [core/template/mobile/](core/template/mobile/) | Templates HTML du widget. |
| [plugin_info/configuration.php](plugin_info/configuration.php) | Configuration générale du plugin (port du démon). |

## Installation

1. Installer le plugin depuis le Market Jeedom (ou manuellement via ce dépôt).
2. Activer le plugin : ses dépendances (Node.js + modules npm) sont installées automatiquement.
3. Dans **Plugins > qBittorrent > Configuration**, définir le port du démon local (laisser vide pour utiliser le port par défaut).
4. Démarrer le démon du plugin.
5. Créer un équipement pour chaque instance qBittorrent à suivre, en renseignant :
   - l'URL de sa WebUI (ex. `http://192.168.1.x:8080`),
   - l'identifiant et le mot de passe de connexion à la WebUI.
6. Les valeurs se rafraîchissent ensuite automatiquement chaque minute (cron natif Jeedom) ; un rafraîchissement manuel est possible via la commande **Rafraichir**.

## Prérequis

- Jeedom ≥ 4.2.
- Node.js disponible sur le serveur Jeedom (installé automatiquement via `resources/install_apt.sh`).
- Une ou plusieurs instances **qBittorrent** avec la **WebUI activée** et accessibles en réseau depuis le serveur Jeedom.

## Limitations connues

- Plugin principalement en lecture/supervision : pas de pilotage complet des torrents (pause, suppression, ajout...) depuis Jeedom, seule la consultation du détail est disponible.
- La fréquence de rafraîchissement dépend du cron Jeedom, pas de flux temps réel.

## Licence

AGPL — voir [LICENSE](LICENSE).
