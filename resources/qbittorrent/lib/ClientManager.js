const Client = require('./Client');
const logger = require('./Logger');

class ClientManager {

    constructor() {
        this.clientList = new Map();
    }
	
	async closeAllConnections() {
		
		for (const client of this.clientList.values()) {
			
			try {
				await client.logout();
			}
			catch(err) {
				logger.error(`Erreur logout client: ${err.message}`, { stack: err.stack });
			}
		}
		this.clientList.clear();
	}
	
	async updateClientList(clients) {
		
		await this.closeAllConnections();
		
		if (typeof clients !== 'undefined') {
			for (const client of clients) {
				if (client['url'] !== '' && client['username'] !== '' && client['password'] !== '') {
					var id = client['url'];
					if (!this.clientList.has(id)) {
						var clientInstance = new Client(client['url'], client['username'], client['password']);
						this.clientList.set(id, clientInstance);
					}
				}
			}
		}
		return this.clientList;
	}
	
	async syncMainData(id) {
        if (this.clientList.has(id)) {
			var client = this.clientList.get(id);
			return await client.syncMainData();
		}
        return null;
    }
	
	async getAppVersion(id) {
		if (this.clientList.has(id)) {
			var client = this.clientList.get(id);
			return await client.getAppVersion();
		}
        return null;
	}
	
	async getApiVersion(id) {
		if (this.clientList.has(id)) {
			var client = this.clientList.get(id);
			return await client.getApiVersion();
		}
        return null;
	}
	
	async getTorrentList(id, hashes) {
		if (this.clientList.has(id)) {
			var client = this.clientList.get(id);
			return await client.getTorrentInfos(hashes);
		}
        return null;
	}
	
	async getTorrentTrackers(id, hash) {
		if (this.clientList.has(id)) {
			var client = this.clientList.get(id);
			return await client.getTorrentTrackers(hash);
		}
        return null;
	}
	
	async getTorrentContents(id, hash, indexes) {
		if (this.clientList.has(id)) {
			var client = this.clientList.get(id);
			return await client.getTorrentContents(hash, indexes);
		}
        return null;
	}
	
	async getTorrentProperties(id, hash) {
		if (this.clientList.has(id)) {
			var client = this.clientList.get(id);
			return await client.getTorrentProperties(hash);
		}
        return null;
	}
	
	async getTransferInfo(id) {
		if (this.clientList.has(id)) {
			var client = this.clientList.get(id);
			return await client.getTransferInfo();
		}
        return null;
	}
}

module.exports = ClientManager;