<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';
require_once __DIR__  . '/../../core/php/qbittorrent.inc.php';

class qbittorrent extends eqLogic {

    /*     * *************************Attributs****************************** */

	private const TORRENT_CMDS = [
		['id' => 'added_on', 'name' => 'Date ajout', 'type' => 'info', 'subType' => 'date', 'unit' => '', 'scale' => ''],
		['id' => 'amount_left', 'name' => 'Données restantes à télécharger', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'MB', 'scale' => '1000000'],
        ['id' => 'progress', 'name' => 'Progression', 'type' => 'info', 'subType' => 'numeric', 'unit' => '%', 'scale' => '0.01'],
        ['id' => 'completed', 'name' => 'Données téléchargées', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'MB', 'scale' => '1000000'],
		['id' => 'dl_limit', 'name' => 'Limite vitesse download du torrent', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'MB/s', 'scale' => '1000000'],
		['id' => 'dlspeed', 'name' => 'Vitesse download du torrent', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'MB/s', 'scale' => '1000000'],
		['id' => 'ratio', 'name' => 'Ratio du torrent', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'scale' => '1'],
		['id' => 'num_leechs', 'name' => 'Nombre de leechs', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'scale' => '1'],
		['id' => 'num_seeds', 'name' => 'Nombre de seeds', 'type' => 'info', 'subType' => 'numeric', 'unit' => '', 'scale' => '1'],
		['id' => 'seeding_time', 'name' => 'Temps total de download', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'min', 'scale' => '60'],
		['id' => 'size', 'name' => 'Taille totale', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'MB', 'scale' => '1000000'],
		['id' => 'state', 'name' => 'Etat du torrent', 'type' => 'info', 'subType' => 'string', 'unit' => '', 'scale' => ''],
		['id' => 'uploaded', 'name' => 'Données totale upload du torrent', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'GB', 'scale' => '1000000000'],
		['id' => 'up_limit', 'name' => 'Limite vitesse upload du torrent', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'MB/s', 'scale' => '1000000'],
		['id' => 'upspeed', 'name' => 'Vitesse upload du torrent', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'MB/s', 'scale' => '1000000'],
    ];
	
	private const CONTENT_CMDS = [
		['id' => 'size_content', 'name' => 'Taille fichier', 'type' => 'info', 'subType' => 'numeric', 'unit' => 'MB', 'scale' => '1000000'],
		['id' => 'progress_content', 'name' => 'Progression fichier', 'type' => 'info', 'subType' => 'numeric', 'unit' => '%', 'scale' => '0.01'],
        ['id' => 'is_seed_content', 'name' => 'En téléchargement ou complet', 'type' => 'info', 'subType' => 'binary', 'unit' => '', 'scale' => ''],
        ['id' => 'priority_content', 'name' => 'Priorité fichier', 'type' => 'info', 'subType' => 'string', 'unit' => '', 'scale' => ''],
		['id' => 'availability_content', 'name' => 'Disponibilité fichier', 'type' => 'info', 'subType' => 'numeric', 'unit' => '%', 'scale' => '0.01'],
    ];
	
	private const CONTENT_PRIORITY = [
		['priority' => 0, 'label' => 'Do not download'],
		['priority' => 1, 'label' => 'Normal priority'],
        ['priority' => 6, 'label' => 'High priority'],
        ['priority' => 7, 'label' => 'Maximal priority'],
    ];


    /*     * ***********************Methode static*************************** */

    /*
    * Fonction exécutée automatiquement toutes les minutes par Jeedom
    */
    public static function cron() {
        qbittorrent::pull();
    }
	
	public static function deamon_info() {
		$return = array();
		$return['log'] = __CLASS__;
		$return['state'] = 'nok';
		$pid_file = jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';
		if (file_exists($pid_file)) {
			if (posix_getsid(trim(file_get_contents($pid_file)))) {
				$return['state'] = 'ok';
			} else {
				shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
			}
		}
		$return['launchable'] = 'nok';
        $port_server = config::byKey('port_server', __CLASS__);
		if ($port_server == '') {
            $return['launchable_message'] = __('Le port serveur n\'est pas configuré', __FILE__);
		}
		else {
			$return['launchable'] = 'ok';
		}
		return $return;
	}
	
	public static function deamon_start($_debug = false) {
		self::deamon_stop();
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') {
			throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
		}
		$port_server = config::byKey('port_server', __CLASS__);
		$path = dirname(__FILE__) . '/../../resources';
		
		$cmd = 'node ' . $path . '/qbittorrent/index.js';
		$cmd .= ' ' . $port_server;
		$cmd .= ' ' . jeedom::getApiKey(__CLASS__);
		$cmd .= ' ' . jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';
		
		log::add(__CLASS__, 'info', 'Lancement démon qbittorrent : ' . $cmd);
		exec($cmd . ' >> /dev/null 2>&1 &');
		$i = 0;
		while ($i < 30) {
			$deamon_info = self::deamon_info();
			if ($deamon_info['state'] == 'ok') {
				break;
			}
			sleep(1);
			$i++;
		}
		if ($i >= 30) {
			log::add(__CLASS__, 'error', 'Impossible de lancer le démon qbittorrent, relancer le démon en debug et vérifiez la log', 'unableStartDeamon');
			return false;
		}
		message::removeAll(__CLASS__, 'unableStartDeamon');
		log::add(__CLASS__, 'info', 'Démon qbittorrent lancé');
		
		self::syncEqLogicWithQbittorrent();
		log::add(__CLASS__, 'info', 'Liste des clients synchronisé');
		return true;
	}
	
	public static function deamon_stop() {
		try {
			$deamon_info = self::deamon_info();
			if ($deamon_info['state'] == 'ok') {
				try {
					callQbittorrent('/stop', null, 30);
				} catch (Exception $e) {
					
				}
			}
			$pid_file = jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';
			if (file_exists($pid_file)) {
				$pid = intval(trim(file_get_contents($pid_file)));
				system::kill($pid);
			}
		} catch (\Exception $e) {
			
		}
	}
	
	public static function syncEqLogicWithQbittorrent() {
		log::add(__CLASS__, 'debug', "syncEqLogicWithQbittorrent()");
		
		$clients = null;
		foreach (self::byType(__CLASS__) as $client) {
			
			if ($client->getIsEnable() == 1) {
				
				$url = $client->getConfiguration('url');
				$username = $client->getConfiguration('username');
				$password = $client->getConfiguration('password');
				$clients['clients'][] = array('url' => $url, 'username' => $username, 'password' => $password);
			}
		}
		$ret = callQbittorrent('/updateClientList', $clients);
	}
	
	public static function pull() {
		log::add(__CLASS__, 'debug', "pull()");
		
		$clients = null;
        foreach (self::byType(__CLASS__) as $eqLogic) {
			if ($eqLogic->getIsEnable() == 1) {
				$url = $eqLogic->getConfiguration('url');
				$username = $eqLogic->getConfiguration('username');
				$password = $eqLogic->getConfiguration('password');
				$clients['clients'][] = array('url' => $url, 'username' => $username, 'password' => $password);
			}
        }
		$ret = callQbittorrent('/updateClientList', $clients);
		foreach (self::byType(__CLASS__) as $eqLogic) {
			if ($eqLogic->getIsEnable() == 1) {
				$eqLogic->updateClient();
			}
        }
    }

	/*     * *********************Méthodes d'instance************************* */
  
	public function updateClient() {
		$url = $this->getConfiguration('url');
		$this->updateAppVersion($url);
		$this->updateApiVersion($url);
		$this->syncMain($url);
		$this->refreshWidget();
	}
	
	public function updateAppVersion($url) {
		$appVersionRet = callQbittorrent('/appVersion?client=' . $url);
		$appVersion = implode('', $appVersionRet);
		$appVersionCmd = $this->getCmd(null, "appVersion");
        if (is_object($appVersionCmd)) {
            if ($appVersionCmd->formatValue($appVersion) != $appVersionCmd->execCmd()) {
				$appVersionCmd->event($appVersion, date('Y-m-d H:i:s'));
            }
        }
	}
	
	public function updateApiVersion($url) {
		$apiVersionRet = callQbittorrent('/apiVersion?client=' . $url);
		$apiVersion = implode('', $apiVersionRet);
		$apiVersionCmd = $this->getCmd(null, "apiVersion");
        if (is_object($apiVersionCmd)) {
            if ($apiVersionCmd->formatValue($apiVersion) != $apiVersionCmd->execCmd()) {
				$apiVersionCmd->event($apiVersion, date('Y-m-d H:i:s'));
            }
        }
	}
	
	public function syncMain($url) {
		$syncMainRet = callQbittorrent('/syncMain?client=' . $url);
		$statusCmd = $this->getCmd(null, "status");
        if (is_object($statusCmd)) {
            if ($statusCmd->formatValue($syncMainRet['server_state']['connection_status']) != $statusCmd->execCmd()) {
				$statusCmd->event($syncMainRet['server_state']['connection_status'], date('Y-m-d H:i:s'));
            }
        }
		$freeSpaceDiskCmd = $this->getCmd(null, "freeSpaceDisk");
        if (is_object($freeSpaceDiskCmd)) {
			$freeSpaceDisk = round(($syncMainRet['server_state']['free_space_on_disk'] / 1000000000), 3);
            if ($freeSpaceDiskCmd->formatValue($freeSpaceDisk) != $freeSpaceDiskCmd->execCmd()) {
				$freeSpaceDiskCmd->event($freeSpaceDisk, date('Y-m-d H:i:s'));
            }
        }
		$allTimeDlCmd = $this->getCmd(null, "allTimeDl");
        if (is_object($allTimeDlCmd)) {
			$allTimeDl = round(($syncMainRet['server_state']['alltime_dl'] / 1000000000), 3);
            if ($allTimeDlCmd->formatValue($allTimeDl) != $allTimeDlCmd->execCmd()) {
				$allTimeDlCmd->event($allTimeDl, date('Y-m-d H:i:s'));
            }
        }
		$allTimeUlCmd = $this->getCmd(null, "allTimeUl");
        if (is_object($allTimeUlCmd)) {
			$allTimeUl = round(($syncMainRet['server_state']['alltime_ul'] / 1000000000), 3);
            if ($allTimeUlCmd->formatValue($allTimeUl) != $allTimeUlCmd->execCmd()) {
				$allTimeUlCmd->event($allTimeUl, date('Y-m-d H:i:s'));
            }
        }
		$dlInfoDataCmd = $this->getCmd(null, "dlInfoData");
        if (is_object($dlInfoDataCmd)) {
			$dlInfoData = round(($syncMainRet['server_state']['dl_info_data'] / 1000000000), 3);
            if ($dlInfoDataCmd->formatValue($dlInfoData) != $dlInfoDataCmd->execCmd()) {
				$dlInfoDataCmd->event($dlInfoData, date('Y-m-d H:i:s'));
            }
        }
		$dlInfoSpeedCmd = $this->getCmd(null, "dlInfoSpeed");
        if (is_object($dlInfoSpeedCmd)) {
			$dlInfoSpeed = round(($syncMainRet['server_state']['dl_info_speed'] / 1000000), 3);
            if ($dlInfoSpeedCmd->formatValue($dlInfoSpeed) != $dlInfoSpeedCmd->execCmd()) {
				$dlInfoSpeedCmd->event($dlInfoSpeed, date('Y-m-d H:i:s'));
            }
        }
		$dlRateLimitCmd = $this->getCmd(null, "dlRateLimit");
        if (is_object($dlRateLimitCmd)) {
			$dlRateLimit = round(($syncMainRet['server_state']['dl_rate_limit'] / 1000000), 3);
            if ($dlRateLimitCmd->formatValue($dlRateLimit) != $dlRateLimitCmd->execCmd()) {
				$dlRateLimitCmd->event($dlRateLimit, date('Y-m-d H:i:s'));
            }
        }
		$upInfoDataCmd = $this->getCmd(null, "upInfoData");
        if (is_object($upInfoDataCmd)) {
			$upInfoData = round(($syncMainRet['server_state']['up_info_data'] / 1000000000), 3);
            if ($upInfoDataCmd->formatValue($upInfoData) != $upInfoDataCmd->execCmd()) {
				$upInfoDataCmd->event($upInfoData, date('Y-m-d H:i:s'));
            }
        }
		$upInfoSpeedCmd = $this->getCmd(null, "upInfoSpeed");
        if (is_object($upInfoSpeedCmd)) {
			$upInfoSpeed = round(($syncMainRet['server_state']['up_info_speed'] / 1000000), 3);
            if ($upInfoSpeedCmd->formatValue($upInfoSpeed) != $upInfoSpeedCmd->execCmd()) {
				$upInfoSpeedCmd->event($upInfoSpeed, date('Y-m-d H:i:s'));
            }
        }
		$upRateLimitCmd = $this->getCmd(null, "upRateLimit");
        if (is_object($upRateLimitCmd)) {
			$upRateLimit = round(($syncMainRet['server_state']['up_rate_limit'] / 1000000), 3);
            if ($upRateLimitCmd->formatValue($upRateLimit) != $upRateLimitCmd->execCmd()) {
				$upRateLimitCmd->event($upRateLimit, date('Y-m-d H:i:s'));
            }
        }
		$useSpeedLimitCmd = $this->getCmd(null, "useSpeedLimit");
        if (is_object($useSpeedLimitCmd)) {
			$useSpeedLimit = $syncMainRet['server_state']['use_alt_speed_limits'] ? 1 : 0;
            if ($useSpeedLimitCmd->formatValue($useSpeedLimit) != $useSpeedLimitCmd->execCmd()) {
				$useSpeedLimitCmd->event($useSpeedLimit, date('Y-m-d H:i:s'));
            }
        }
		$globalRatioCmd = $this->getCmd(null, "globalRatio");
        if (is_object($globalRatioCmd)) {
            if ($globalRatioCmd->formatValue($syncMainRet['server_state']['global_ratio']) != $globalRatioCmd->execCmd()) {
				$globalRatioCmd->event($syncMainRet['server_state']['global_ratio'], date('Y-m-d H:i:s'));
            }
        }
		$queueingCmd = $this->getCmd(null, "queueing");
        if (is_object($queueingCmd)) {
			$queueing = $syncMainRet['server_state']['queueing'] ? 1 : 0;
            if ($queueingCmd->formatValue($queueing) != $queueingCmd->execCmd()) {
				$queueingCmd->event($queueing, date('Y-m-d H:i:s'));
            }
        }
		
		$torrentList = $this->getCmd(null,'torrentList');
        if (is_object($torrentList)) {
            if (count($syncMainRet['torrents']) > 0) {
                $torrentList->setIsVisible(1);
                $list = "";
                foreach ($syncMainRet['torrents'] as $id => $torrent) {
                    $list = $list . $separator . $id  . '|' . $torrent['name'];
                    $separator = ';';
                }
                $torrentList->setConfiguration('listValue', $list);
            }
            else {
                $torrentList->setIsVisible(0);
                // Sans ce reset, l'ancienne liste (d'une synchro précédente où il
                // y avait des torrents) reste en mémoire dans la config et continue
                // d'être affichée dans le widget, même si l'API renvoie torrents:[].
                $torrentList->setConfiguration('listValue', '');
				$this->saveTorrentIdValue('');
            }
            $torrentList->save();
        }
		if ($this->getTorrentId() !== '') {
			if (array_key_exists($this->getTorrentId(), $syncMainRet['torrents'])) {
				$this->updateTorrentCmds($syncMainRet['torrents'][$this->getTorrentId()]);
				$this->setVisibleTorrentCmds(1);
			}
			else {
				$this->setVisibleTorrentCmds(0);
			}
		}
		else {
			$this->setVisibleTorrentCmds(0);
		}
		$this->setVisibleContentCmds(0);
		$this->setVisibleContentList(0);
		$this->saveContentIdValue('');
		log::add(__CLASS__, 'debug', json_encode($syncMainRet));
	}
	
	public function updateTorrentInfos($torrentId) {
        $this->saveTorrentIdValue($torrentId);
		$url = $this->getConfiguration('url');
		$torrentListRet = callQbittorrent('/torrentList?client=' . $url . '&hashes=' . $torrentId);
		if (count($torrentListRet) > 0) {
			$this->updateTorrentCmds($torrentListRet[0]);
			$this->setVisibleTorrentCmds(1);
		}
		else {
			$this->setVisibleTorrentCmds(0);
		}
		log::add(__CLASS__, 'debug', json_encode($torrentListRet));
		
		$torrentContentsRet = callQbittorrent('/torrentContents?client=' . $url . '&hash=' . $torrentId);
		$contentList = $this->getCmd(null,'contentList');
		if (is_object($contentList)) {
			if (count($torrentContentsRet) > 0) {
				$contentList->setIsVisible(1);
                $list = "";
                foreach ($torrentContentsRet as $content) {
                    $list = $list . $separator . $content['index']  . '|' . $content['name'];
                    $separator = ';';
                }
                $contentList->setConfiguration('listValue', $list);
            }
            else {
                $contentList->setIsVisible(0);
                // Même correctif que pour torrentList : sans ça, l'ancienne liste
                // de fichiers d'un torrent précédemment sélectionné continue de
                // s'afficher pour un torrent qui n'en a pas (ou plus).
                $contentList->setConfiguration('listValue', '');
                $this->saveContentIdValue('');
            }
            $contentList->save();
        }
		$this->setVisibleContentCmds(0);
		$this->saveContentIdValue('');
		log::add(__CLASS__, 'debug', json_encode($torrentContentsRet));

        $this->refreshWidget();
    }
	
	public function updateContentInfos($contentId) {
		$this->saveContentIdValue($contentId);
		$url = $this->getConfiguration('url');
		$torrentId = $this->getTorrentId();
		$contentListRet = callQbittorrent('/torrentContents?client=' . $url . '&hash=' . $torrentId . '&indexes=' . $contentId);
		if (count($contentListRet) > 0) {
			$this->updateContentCmds($contentListRet[0]);
			$this->setVisibleContentCmds(1);
		}
		else {
			$this->setVisibleContentCmds(0);
		}
		log::add(__CLASS__, 'debug', json_encode($contentListRet));
		
		$this->refreshWidget();
	}
	
	public function saveTorrentIdValue($torrentId) { 
        $torrentIdCmd = $this->getCmd(null,'torrentId');
        if (is_object($torrentIdCmd)) {
            if ($torrentIdCmd->formatValue($torrentId) != $torrentIdCmd->execCmd()) {
				$torrentIdCmd->event($torrentId, date('Y-m-d H:i:s'));
            }
        }
    }
	
	public function saveContentIdValue($contentId) { 
        $contentIdCmd = $this->getCmd(null,'contentId');
        if (is_object($contentIdCmd)) {
            if ($contentIdCmd->formatValue($contentId) != $contentIdCmd->execCmd()) {
				$contentIdCmd->event($contentId, date('Y-m-d H:i:s'));
            }
        }
    }
	
	public function getTorrentId() { 
        $torrentIdCmd = $this->getCmd(null,'torrentId');
        if (is_object($torrentIdCmd)) {
			return $torrentIdCmd->execCmd();
        }
		return '';
    }
	
	public function updateTorrentCmds($torrent) {
		foreach (self::TORRENT_CMDS as $torrentCmd) {
			$cmd = $this->getCmd(null, $torrentCmd['id']);
			if (is_object($cmd)) {
				$value = $torrent[$torrentCmd['id']];
				if ($torrentCmd['subType'] == 'numeric') {
					$value = round(($torrent[$torrentCmd['id']] / $torrentCmd['scale']), 3);
				}
				else if ($torrentCmd['subType'] == 'date') {
					$value = date('d/m/Y H:i:s', $torrent[$torrentCmd['id']]);
				}
				if ($cmd->formatValue($value) != $cmd->execCmd()) {
					$cmd->event($value, date('Y-m-d H:i:s'));
				}
			}
		}
	}
	
	public function updateContentCmds($content) {
		foreach (self::CONTENT_CMDS as $contentCmd) {
			$cmd = $this->getCmd(null, $contentCmd['id']);
			if (is_object($cmd)) {
				$property = str_replace('_content', '', $contentCmd['id']);
				$value = $content[$property];
				if ($contentCmd['id'] == 'priority_content') {
					foreach (self::CONTENT_PRIORITY as $priority) {
						if ($priority['priority'] == $content[$property]) {
							$value = $priority['label'];
							break;
						}
					}
				}
				if ($contentCmd['subType'] == 'numeric') {
					$value = round(($content[$property] / $contentCmd['scale']), 3);
				}
				else if ($contentCmd['subType'] == 'date') {
					$value = date('d/m/Y H:i:s', $content[$property]);
				}
				if ($cmd->formatValue($value) != $cmd->execCmd()) {
					$cmd->event($value, date('Y-m-d H:i:s'));
				}
			}
		}
	}
	
	public function setVisibleTorrentCmds($value) {
		foreach (self::TORRENT_CMDS as $torrentCmd) {
			$cmd = $this->getCmd(null, $torrentCmd['id']);
			if (is_object($cmd)) {
				$cmd->setIsVisible($value);
				$cmd->save();
			}
		}
	}
	
	public function setVisibleContentCmds($value) {
		foreach (self::CONTENT_CMDS as $contentCmd) {
			$cmd = $this->getCmd(null, $contentCmd['id']);
			if (is_object($cmd)) {
				$cmd->setIsVisible($value);
				$cmd->save();
			}
		}
	}
	
	public function setVisibleContentList($value) {
		$cmd = $this->getCmd(null, 'contentList');
		if (is_object($cmd)) {
			$cmd->setIsVisible($value);
			$cmd->save();
		}
	}

	// Fonction exécutée automatiquement avant la création de l'équipement
	public function preInsert() {
	}

    // Fonction exécutée automatiquement après la création de l'équipement
    public function postInsert() {
	    self::syncEqLogicWithQbittorrent();
    }

    // Fonction exécutée automatiquement avant la mise à jour de l'équipement
    public function preUpdate() {
    }

    // Fonction exécutée automatiquement après la mise à jour de l'équipement
    public function postUpdate() {
    }

	// Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement
	public function preSave() {
	}

    // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
    public function postSave() {
		$refresh = $this->getCmd(null, 'refresh');
        if (!is_object($refresh)) {
            $refresh = new qbittorrentCmd();
        }
        $refresh->setName('Rafraichir');
        $refresh->setEqLogic_id($this->getId());
        $refresh->setLogicalId('refresh');
        $refresh->setType('action');
        $refresh->setSubType('other');
        $refresh->setOrder(0);
        $refresh->save();
		
		$appVersion = $this->getCmd(null, "appVersion");
        if (!is_object($appVersion)) {
            $appVersion = new qbittorrentCmd();
        }
        $appVersion->setName("Version");
        $appVersion->setEqLogic_id($this->getId());
        $appVersion->setLogicalId("appVersion");
        $appVersion->setType('info');
        $appVersion->setSubType('string');
        $appVersion->setOrder(1);
        $appVersion->save();
		
		$apiVersion = $this->getCmd(null, "apiVersion");
        if (!is_object($apiVersion)) {
            $apiVersion = new qbittorrentCmd();
        }
        $apiVersion->setName("Version API");
        $apiVersion->setEqLogic_id($this->getId());
        $apiVersion->setLogicalId("apiVersion");
        $apiVersion->setType('info');
        $apiVersion->setSubType('string');
        $apiVersion->setOrder(2);
        $apiVersion->save();
		
		$status = $this->getCmd(null, "status");
        if (!is_object($status)) {
            $status = new qbittorrentCmd();
        }
        $status->setName("Etat");
        $status->setEqLogic_id($this->getId());
        $status->setLogicalId("status");
        $status->setType('info');
        $status->setSubType('string');
		$status->setDisplay('forceReturnLineAfter', '1');
        $status->setOrder(3);
        $status->save();
		
		$freeSpaceDisk = $this->getCmd(null, "freeSpaceDisk");
        if (!is_object($freeSpaceDisk)) {
            $freeSpaceDisk = new qbittorrentCmd();
        }
        $freeSpaceDisk->setName("Espace disponible");
        $freeSpaceDisk->setEqLogic_id($this->getId());
        $freeSpaceDisk->setLogicalId("freeSpaceDisk");
        $freeSpaceDisk->setType('info');
		$freeSpaceDisk->setSubType('numeric');
        $freeSpaceDisk->setUnite("GB");
        $freeSpaceDisk->setTemplate('dashboard', 'line');
        $freeSpaceDisk->setTemplate('mobile', 'line');
		$freeSpaceDisk->setDisplay('forceReturnLineAfter', '1');
        $freeSpaceDisk->setOrder(4);
        $freeSpaceDisk->save();
		
		$allTimeDl = $this->getCmd(null, "allTimeDl");
        if (!is_object($allTimeDl)) {
            $allTimeDl = new qbittorrentCmd();
        }
        $allTimeDl->setName("Taille totale download");
        $allTimeDl->setEqLogic_id($this->getId());
        $allTimeDl->setLogicalId("allTimeDl");
        $allTimeDl->setType('info');
		$allTimeDl->setSubType('numeric');
        $allTimeDl->setUnite("GB");
        $allTimeDl->setTemplate('dashboard', 'line');
        $allTimeDl->setTemplate('mobile', 'line');
		$allTimeDl->setDisplay('forceReturnLineAfter', '1');
        $allTimeDl->setOrder(5);
        $allTimeDl->save();
		
		$allTimeUl = $this->getCmd(null, "allTimeUl");
        if (!is_object($allTimeUl)) {
            $allTimeUl = new qbittorrentCmd();
        }
        $allTimeUl->setName("Taille totale upload");
        $allTimeUl->setEqLogic_id($this->getId());
        $allTimeUl->setLogicalId("allTimeUl");
        $allTimeUl->setType('info');
		$allTimeUl->setSubType('numeric');
        $allTimeUl->setUnite("GB");
        $allTimeUl->setTemplate('dashboard', 'line');
        $allTimeUl->setTemplate('mobile', 'line');
		$allTimeUl->setDisplay('forceReturnLineAfter', '1');
        $allTimeUl->setOrder(6);
        $allTimeUl->save();
		
		$dlInfoData = $this->getCmd(null, "dlInfoData");
        if (!is_object($dlInfoData)) {
            $dlInfoData = new qbittorrentCmd();
        }
        $dlInfoData->setName("Taille download session");
        $dlInfoData->setEqLogic_id($this->getId());
        $dlInfoData->setLogicalId("dlInfoData");
        $dlInfoData->setType('info');
		$dlInfoData->setSubType('numeric');
        $dlInfoData->setUnite("GB");
        $dlInfoData->setTemplate('dashboard', 'line');
        $dlInfoData->setTemplate('mobile', 'line');
		$dlInfoData->setDisplay('forceReturnLineAfter', '1');
        $dlInfoData->setOrder(7);
        $dlInfoData->save();
		
		$dlInfoSpeed = $this->getCmd(null, "dlInfoSpeed");
        if (!is_object($dlInfoSpeed)) {
            $dlInfoSpeed = new qbittorrentCmd();
        }
        $dlInfoSpeed->setName("Vitesse download");
        $dlInfoSpeed->setEqLogic_id($this->getId());
        $dlInfoSpeed->setLogicalId("dlInfoSpeed");
        $dlInfoSpeed->setType('info');
		$dlInfoSpeed->setSubType('numeric');
        $dlInfoSpeed->setUnite("MB/s");
        $dlInfoSpeed->setTemplate('dashboard', 'line');
        $dlInfoSpeed->setTemplate('mobile', 'line');
		$dlInfoSpeed->setDisplay('forceReturnLineAfter', '1');
        $dlInfoSpeed->setOrder(8);
        $dlInfoSpeed->save();
		
		$dlRateLimit = $this->getCmd(null, "dlRateLimit");
        if (!is_object($dlRateLimit)) {
            $dlRateLimit = new qbittorrentCmd();
        }
        $dlRateLimit->setName("Limite vitesse download");
        $dlRateLimit->setEqLogic_id($this->getId());
        $dlRateLimit->setLogicalId("dlRateLimit");
        $dlRateLimit->setType('info');
		$dlRateLimit->setSubType('numeric');
        $dlRateLimit->setUnite("MB/s");
        $dlRateLimit->setTemplate('dashboard', 'line');
        $dlRateLimit->setTemplate('mobile', 'line');
		$dlRateLimit->setDisplay('forceReturnLineAfter', '1');
        $dlRateLimit->setOrder(9);
        $dlRateLimit->save();
		
		$upInfoData = $this->getCmd(null, "upInfoData");
        if (!is_object($upInfoData)) {
            $upInfoData = new qbittorrentCmd();
        }
        $upInfoData->setName("Taille upload session");
        $upInfoData->setEqLogic_id($this->getId());
        $upInfoData->setLogicalId("upInfoData");
        $upInfoData->setType('info');
		$upInfoData->setSubType('numeric');
        $upInfoData->setUnite("GB");
        $upInfoData->setTemplate('dashboard', 'line');
        $upInfoData->setTemplate('mobile', 'line');
		$upInfoData->setDisplay('forceReturnLineAfter', '1');
        $upInfoData->setOrder(10);
        $upInfoData->save();
		
		$upInfoSpeed = $this->getCmd(null, "upInfoSpeed");
        if (!is_object($upInfoSpeed)) {
            $upInfoSpeed = new qbittorrentCmd();
        }
        $upInfoSpeed->setName("Vitesse upload");
        $upInfoSpeed->setEqLogic_id($this->getId());
        $upInfoSpeed->setLogicalId("upInfoSpeed");
        $upInfoSpeed->setType('info');
		$upInfoSpeed->setSubType('numeric');
        $upInfoSpeed->setUnite("MB/s");
        $upInfoSpeed->setTemplate('dashboard', 'line');
        $upInfoSpeed->setTemplate('mobile', 'line');
		$upInfoSpeed->setDisplay('forceReturnLineAfter', '1');
        $upInfoSpeed->setOrder(11);
        $upInfoSpeed->save();
		
		$upRateLimit = $this->getCmd(null, "upRateLimit");
        if (!is_object($upRateLimit)) {
            $upRateLimit = new qbittorrentCmd();
        }
        $upRateLimit->setName("Limite vitesse upload");
        $upRateLimit->setEqLogic_id($this->getId());
        $upRateLimit->setLogicalId("upRateLimit");
        $upRateLimit->setType('info');
		$upRateLimit->setSubType('numeric');
        $upRateLimit->setUnite("MB/s");
        $upRateLimit->setTemplate('dashboard', 'line');
        $upRateLimit->setTemplate('mobile', 'line');
		$upRateLimit->setDisplay('forceReturnLineAfter', '1');
        $upRateLimit->setOrder(12);
        $upRateLimit->save();
		
		$useSpeedLimit = $this->getCmd(null, "useSpeedLimit");
        if (!is_object($useSpeedLimit)) {
            $useSpeedLimit = new qbittorrentCmd();
        }
        $useSpeedLimit->setName("Limite vitesse");
        $useSpeedLimit->setEqLogic_id($this->getId());
        $useSpeedLimit->setLogicalId("useSpeedLimit");
        $useSpeedLimit->setType('info');
		$useSpeedLimit->setSubType('binary');
        $useSpeedLimit->setTemplate('dashboard', 'line');
        $useSpeedLimit->setTemplate('mobile', 'line');
		$useSpeedLimit->setDisplay('forceReturnLineAfter', '1');
        $useSpeedLimit->setOrder(13);
        $useSpeedLimit->save();
		
		$globalRatio = $this->getCmd(null, "globalRatio");
        if (!is_object($globalRatio)) {
            $globalRatio = new qbittorrentCmd();
        }
        $globalRatio->setName("Ratio DL-UP global");
        $globalRatio->setEqLogic_id($this->getId());
        $globalRatio->setLogicalId("globalRatio");
        $globalRatio->setType('info');
		$globalRatio->setSubType('numeric');
        $globalRatio->setTemplate('dashboard', 'line');
        $globalRatio->setTemplate('mobile', 'line');
		$globalRatio->setDisplay('forceReturnLineAfter', '1');
        $globalRatio->setOrder(14);
        $globalRatio->save();
		
		$queueing = $this->getCmd(null, "queueing");
        if (!is_object($queueing)) {
            $queueing = new qbittorrentCmd();
        }
        $queueing->setName("File d'attente");
        $queueing->setEqLogic_id($this->getId());
        $queueing->setLogicalId("queueing");
        $queueing->setType('info');
		$queueing->setSubType('binary');
        $queueing->setTemplate('dashboard', 'line');
        $queueing->setTemplate('mobile', 'line');
		$queueing->setDisplay('forceReturnLineAfter', '1');
        $queueing->setOrder(15);
        $queueing->save();
		
		$torrentId = $this->getCmd(null,'torrentId');
        if (!is_object($torrentId)) {
            $torrentId = new qbittorrentCmd();
        }
        $torrentId->setName("Id Torrent");
        $torrentId->setEqLogic_id($this->getId());
        $torrentId->setLogicalId("torrentId");
        $torrentId->setType('info');
        $torrentId->setSubType('string');
        $torrentId->setIsVisible(0);
        $torrentId->setOrder(16);
        $torrentId->save();
        
        $torrentList = $this->getCmd(null,'torrentList');
        if (!is_object($torrentList)) {
            $torrentList = new qbittorrentCmd();
        }
        $torrentList->setName("Liste torrents");
        $torrentList->setEqLogic_id($this->getId());
        $torrentList->setLogicalId("torrentList");
        $torrentList->setType('action');
        $torrentList->setSubType('select');
        $torrentList->setValue($this->getCmd(null,'torrentId')->getId());
        $torrentList->setOrder(17);
        $torrentList->save();
		
		$order = $this->createCmds(self::TORRENT_CMDS, 17);
		
		$contentId = $this->getCmd(null,'contentId');
        if (!is_object($contentId)) {
            $contentId = new qbittorrentCmd();
        }
        $contentId->setName("Id Content");
        $contentId->setEqLogic_id($this->getId());
        $contentId->setLogicalId("contentId");
        $contentId->setType('info');
        $contentId->setSubType('string');
        $contentId->setIsVisible(0);
        $contentId->setOrder($order);
        $contentId->save();
		$order++;
        
        $contentList = $this->getCmd(null,'contentList');
        if (!is_object($contentList)) {
            $contentList = new qbittorrentCmd();
        }
        $contentList->setName("Liste fichiers");
        $contentList->setEqLogic_id($this->getId());
        $contentList->setLogicalId("contentList");
        $contentList->setType('action');
        $contentList->setSubType('select');
        $contentList->setValue($this->getCmd(null,'contentId')->getId());
		$contentList->setIsVisible(0);
        $contentList->setOrder($order);
        $contentList->save();
		
		$order = $this->createCmds(self::CONTENT_CMDS, $order);
    }
	
	public function createCmds($arrayCmds, $startOrder) {
		$order = $startOrder + 1;
		foreach ($arrayCmds as $cmdObj) {
			$cmd = $this->getCmd(null, $cmdObj['id']);
			if (!is_object($cmd)) {
				$cmd = new qbittorrentCmd();
			}
			$cmd->setName($cmdObj['name']);
			$cmd->setEqLogic_id($this->getId());
			$cmd->setLogicalId($cmdObj['id']);
			$cmd->setType($cmdObj['type']);
			if (($cmdObj['subType'] == 'date') || ($cmdObj['subType'] == 'string')) {
				$cmd->setSubType('string');
			}
			else {
				$cmd->setSubType($cmdObj['subType']);
			}
			$cmd->setTemplate('dashboard', 'line');
			$cmd->setTemplate('mobile', 'line');
			$cmd->setDisplay('forceReturnLineAfter', '1');
			$cmd->setIsVisible(0);
			if ($cmdObj['subType'] == 'numeric') {
				$cmd->setDisplay('parameters', array('scale' => $cmdObj['scale']));
				$cmd->setUnite($cmdObj['unit']);
			}
			$cmd->setOrder($order);
			$order++;
			$cmd->save();
		}
		return $order;
	}

    // Fonction exécutée automatiquement avant la suppression de l'équipement
    public function preRemove() {
    }

    // Fonction exécutée automatiquement après la suppression de l'équipement
    public function postRemove() {
	    self::syncEqLogicWithQbittorrent();
    }


 /*
  * Permet de modifier l'affichage du widget (également utilisable par les commandes)
  */
  public function toHtml($_version = 'dashboard') {
    if ($this->getIsEnable() != 1) {
      return '';
    }

    $replace = $this->preToHtml($_version);
    if (!is_array($replace)) {
      return $replace;
    }
    $version = jeedom::versionAlias($_version);

    $get = function ($logicalId, $default = '-') {
      $cmd = $this->getCmd(null, $logicalId);
      if (!is_object($cmd)) {
        return $default;
      }
      $val = $cmd->execCmd();
      return ($val === '' || $val === null) ? $default : $val;
    };

    $hist = function ($logicalId) {
      $cmd = $this->getCmd(null, $logicalId);
      if (!is_object($cmd)) {
        return array('id' => '0', 'class' => '');
      }
      return array(
        'id' => $cmd->getId(),
        'class' => ($cmd->getIsHistorized() == 1 ? 'cursor history' : ''),
      );
    };

    // Construit les <option> d'un select à partir d'une commande dont la
    // configuration 'listValue' est au format "id1|label1;id2|label2;..."
    $buildOptions = function ($cmd, $currentId, $placeholder) {
      $options = '<option value="">' . $placeholder . '</option>';
      if (!is_object($cmd)) {
        return $options;
      }
      $listValue = $cmd->getConfiguration('listValue', '');
      if ($listValue == '') {
        return $options;
      }
      foreach (explode(';', $listValue) as $entry) {
        if ($entry === '') {
          continue;
        }
        $parts = explode('|', $entry, 2);
        if (count($parts) != 2) {
          continue;
        }
        $selected = ($parts[0] === $currentId) ? ' selected' : '';
        $options .= '<option value="' . htmlspecialchars($parts[0]) . '"' . $selected . '>' . htmlspecialchars($parts[1]) . '</option>';
      }
      return $options;
    };

    $replace['#name_display#'] = $this->getName();

    $cmdRefresh = $this->getCmd('action', 'refresh');
    $replace['#cmd_refresh_id#'] = is_object($cmdRefresh) ? $cmdRefresh->getId() : '0';

    $status = $get('status', '-');
    $replace['#status#'] = $status;
    $replace['#status_class#'] = (strtolower($status) === 'connected') ? 'qbt-online' : 'qbt-offline';

    foreach (array('freeSpaceDisk', 'allTimeDl', 'allTimeUl', 'dlInfoSpeed', 'dlRateLimit', 'upInfoSpeed', 'upRateLimit', 'globalRatio') as $k) {
      $h = $hist($k);
      $replace['#' . $k . '#'] = $get($k, '-');
      $replace['#' . $k . '_id#'] = $h['id'];
      $replace['#' . $k . '_history_class#'] = $h['class'];
    }

    $replace['#speedlimit_label#'] = (intval($get('useSpeedLimit', 0)) == 1) ? 'Oui' : 'Non';
    $replace['#queueing_label#'] = (intval($get('queueing', 0)) == 1) ? 'Oui' : 'Non';

    /* ---------- Sélecteur + détail torrent ---------- */
    $cmdTorrentList = $this->getCmd('action', 'torrentList');
    $replace['#cmd_torrentlist_id#'] = is_object($cmdTorrentList) ? $cmdTorrentList->getId() : '0';
    $currentTorrentId = $this->getTorrentId();
    $replace['#torrent_options#'] = $buildOptions($cmdTorrentList, $currentTorrentId, '-- Choisir un torrent --');

    $hasTorrentSelected = ($currentTorrentId !== '' && $currentTorrentId !== null);
    $replace['#torrent_details_style#'] = $hasTorrentSelected ? '' : 'display:none;';

    foreach (array('state', 'progress', 'dlspeed', 'upspeed', 'num_seeds', 'num_leechs', 'ratio', 'size', 'completed', 'amount_left', 'added_on', 'seeding_time', 'dl_limit', 'up_limit', 'uploaded') as $k) {
      if ($hasTorrentSelected) {
        $h = $hist($k);
        $replace['#' . $k . '#'] = $get($k, '-');
        $replace['#' . $k . '_id#'] = $h['id'];
        $replace['#' . $k . '_history_class#'] = $h['class'];
      } else {
        $replace['#' . $k . '#'] = '-';
        $replace['#' . $k . '_id#'] = '0';
        $replace['#' . $k . '_history_class#'] = '';
      }
    }
    $progressVal = $get('progress', 0);
    $replace['#progress_pct#'] = ($hasTorrentSelected && is_numeric($progressVal)) ? max(0, min(100, round($progressVal))) : 0;

    /* ---------- Sélecteur + détail fichier (contenu du torrent) ---------- */
    $cmdContentList = $this->getCmd('action', 'contentList');
    $replace['#cmd_contentlist_id#'] = is_object($cmdContentList) ? $cmdContentList->getId() : '0';
    $currentContentId = $get('contentId', '');
    $hasContentList = (is_object($cmdContentList) && $cmdContentList->getConfiguration('listValue', '') != '');
    $replace['#content_options#'] = $buildOptions($cmdContentList, $currentContentId, '-- Choisir un fichier --');
    $replace['#content_selector_style#'] = ($hasTorrentSelected && $hasContentList) ? '' : 'display:none;';

    $hasContentSelected = ($currentContentId !== '' && $currentContentId !== null);
    $replace['#content_details_style#'] = $hasContentSelected ? '' : 'display:none;';
    foreach (array('size_content', 'progress_content', 'priority_content', 'availability_content') as $k) {
      $h = $hist($k);
      $replace['#' . $k . '#'] = $hasContentSelected ? $get($k, '-') : '-';
      $replace['#' . $k . '_id#'] = $h['id'];
      $replace['#' . $k . '_history_class#'] = $h['class'];
    }
    $progressContentVal = $get('progress_content', 0);
    $replace['#progress_content_pct#'] = ($hasContentSelected && is_numeric($progressContentVal)) ? max(0, min(100, round($progressContentVal))) : 0;

    return template_replace($replace, getTemplate('core', $version, 'qbittorrent', 'qbittorrent'));
  }
  

  /*     * **********************Getteur Setteur*************************** */
}

class qbittorrentCmd extends cmd {
  /*     * *************************Attributs****************************** */

  /*
  public static $_widgetPossibility = array();
  */

  /*     * ***********************Methode static*************************** */


  /*     * *********************Methode d'instance************************* */

  /*
  * Permet d'empêcher la suppression des commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
  public function dontRemoveCmd() {
    return true;
  }
  */

	// Exécution d'une commande
	public function execute($_options = array()) {
		$eqLogic = $this->getEqLogic();
        if (!is_object($eqLogic) || $eqLogic->getIsEnable() != 1) {
            throw new Exception(__('Equipement desactivé impossible d\éxecuter la commande : ' . $this->getHumanName(), __FILE__));
        }
        log::add('qbittorrent','debug','command: '.$this->getLogicalId().' parameters: '.json_encode($_options));
        switch ($this->getLogicalId()) {
            case "refresh":
                $eqLogic->updateClient();
                return true;
			case "torrentList":
                $eqLogic->updateTorrentInfos($_options['select']);
                return true;
			case "contentList":
                $eqLogic->updateContentInfos($_options['select']);
                return true;
			default:
                return false;
		}
	}

  /*     * **********************Getteur Setteur*************************** */
}
