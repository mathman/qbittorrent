# Plugin qBittorrent pour Jeedom

## ⚠️ Avertissement

Ce plugin est une intégration **non officielle**, développée indépendamment de qBittorrent. Il
n'est ni développé ni maintenu par l'équipe qBittorrent.

Il s'appuie sur un **démon Node.js** local qui dialogue avec la **WebUI** de chaque instance
qBittorrent à suivre : il n'y a donc aucun accès au cloud, tout reste sur votre réseau local.

Le plugin est principalement en **lecture/supervision** : la sélection d'un torrent ou d'un
fichier permet d'en consulter le détail, mais aucun pilotage (pause, suppression, ajout de
torrent...) n'est proposé depuis Jeedom.

## Prérequis

- Jeedom ≥ 4.2
- Node.js disponible sur le serveur Jeedom (installé automatiquement à l'activation du plugin)
- Une ou plusieurs instances **qBittorrent** avec la **WebUI activée** et accessibles en réseau
  depuis le serveur Jeedom

## Installation

1. Installer le plugin depuis le Market Jeedom (ou en manuel via le zip)
2. Activer le plugin : ses dépendances (Node.js + modules npm) sont installées automatiquement
3. Aller dans **Plugins > qBittorrent > Configuration** et renseigner le port du démon local
   (laisser vide pour utiliser le port par défaut), puis démarrer le démon
4. Créer un équipement pour chaque instance qBittorrent à suivre, en renseignant :
   - l'URL de sa WebUI (ex. `http://192.168.1.x:8080`)
   - l'identifiant et le mot de passe de connexion à la WebUI

Les valeurs se rafraîchissent ensuite automatiquement chaque minute. Un rafraîchissement manuel
est possible via la commande **Rafraichir** de l'équipement.

## Capteurs disponibles

### Informations générales du client

- État de connexion, espace disque disponible
- Volumes total et session (download / upload), vitesses instantanées
- Limites de vitesse download / upload configurées, activation de la limite alternative
- Ratio global de partage
- État de la file d'attente

### Détail d'un torrent (via le sélecteur de torrent)

- État, progression, vitesses de download / upload
- Nombre de seeds / leechs, ratio
- Taille totale, données téléchargées / restantes / uploadées
- Date d'ajout, temps total de partage
- Limites de vitesse propres au torrent

### Détail d'un fichier (via le sélecteur de fichier, une fois un torrent sélectionné)

- Taille, progression, disponibilité
- Priorité de téléchargement
- État téléchargement / complet

## Widgets dashboard

Chaque équipement dispose d'un widget dédié affichant l'état du client, ses vitesses de
transfert, l'espace disque disponible et le ratio global, avec un sélecteur de torrent qui ouvre
une pop-up de détail (progression, seeds/leechs, ratio...) et, à l'intérieur, un sélecteur de
fichier pour le détail par fichier du torrent.

## Limites connues

- Pas de pilotage des torrents (pause, suppression, ajout...) depuis Jeedom : consultation
  uniquement.
- Rafraîchissement limité à la fréquence du cron Jeedom (chaque minute), pas de flux temps réel.
- Le démon doit être démarré pour que le plugin fonctionne : sans lui, aucune donnée ne peut être
  récupérée auprès des instances qBittorrent.

## Dépannage

- **Démon injoignable / non démarré** : vérifier dans **Plugins > qBittorrent > Configuration**
  que le démon est bien lancé (état "ok") ; consulter ses logs si besoin.
- **"Le port serveur n'est pas configuré"** : renseigner (ou laisser vide pour la valeur par
  défaut) le champ port du démon avant de le démarrer.
- **Équipement toujours "Déconnecté"** : vérifier l'URL, l'identifiant et le mot de passe de la
  WebUI qBittorrent, ainsi que l'accès réseau entre le serveur Jeedom et l'instance qBittorrent.
- **La liste des torrents/fichiers ne se met pas à jour** : cliquer sur **Rafraichir** sur
  l'équipement, ou vérifier les logs du plugin.
