const axios = require('axios');
const logger = require('./Logger');

const EMPTY_SERVER_STATE = {
    dl_info_speed: 0,
    dl_info_data: 0,
    up_info_speed: 0,
    up_info_data: 0,
    dl_rate_limit: 0,
    up_rate_limit: 0,
    dht_nodes: 0,
    connection_status: 'disconnected',
};

class Client {

    constructor(url, username, password) {
        this.url      = url;
        this.username = username;
        this.password = password;

        this.authCookie        = undefined; // Cookie SID courant
        this.connectionPromise = null;      // Promise unique de connexion

        this.syncRids = {
            mainData: Promise.resolve(0)
        };

        this.syncStates = {
            mainData: {
                categories:   {},
                server_state: EMPTY_SERVER_STATE,
                tags:         [],
                torrents:     {},
                trackers:     {},
            }
        };

        this.isMainDataPending = false;

        // Lancer UNE SEULE connexion initiale
        this.connectionPromise = this._connect();
    }

    // ─────────────────────────────────────────────
    // CONNEXION — une seule Promise à la fois
    // ─────────────────────────────────────────────

    _connect() {
        // Si une connexion est déjà en cours, retourner la même Promise
        if (this.connectionPromise) {
            return this.connectionPromise;
        }

        this.connectionPromise = this._authenticate()
            .then((cookie) => {
                this.authCookie = cookie;
                logger.info(`[Client] Connecté à ${this.url} (${cookie.split('=')[0]})`);
            })
            .catch((err) => {
                this.authCookie = undefined;
                logger.error(`[Client] Échec connexion (${this.url}): ${err.message}`, { stack: err.stack });
                throw err;
            })
            .finally(() => {
                // Libérer le verrou après connexion
                this.connectionPromise = null;
            });

        return this.connectionPromise;
    }

    async _authenticate() {
        const response = await axios.post(
            `${this.url}/api/v2/auth/login`,
            new URLSearchParams({
                username: this.username,
                password: this.password,
            }),
            {
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Referer': this.url,
                    'Origin':  this.url,
                },
                timeout: 10000,
                validateStatus: (status) => status < 500,
            }
        );

        // 200 avec "Ok." ou 204 sans body = succès
        const isSuccess = (response.status === 200 && response.data !== 'Fails.')
                       || response.status === 204;

        if (!isSuccess) {
            throw new Error(
                `Login échoué: status=${response.status} data=${response.data}`
            );
        }

        const cookies = response.headers['set-cookie'];

        if (Array.isArray(cookies) && cookies.length > 0) {
            // Accepter SID= et QBT_SID_XXXX=
            const sidCookie = cookies.find((c) =>
                c.match(/(?:SID=|QBT_SID_\d+=)/)
            );
            if (sidCookie) {
                return sidCookie.split(';')[0].trim();
            }
        }

        // 204 sans cookie = bypass auth actif, pas de cookie nécessaire
        if (response.status === 204) {
            logger.warn(`[Client] Bypass auth actif sur ${this.url}`);
            return '';
        }

        throw new Error(`Cookie SID non trouvé dans la réponse`);
    }

    // ─────────────────────────────────────────────
    // HEADERS
    // ─────────────────────────────────────────────

    async _getHeaders() {
        // Attendre si une connexion est en cours
        if (this.connectionPromise) {
            await this.connectionPromise.catch(() => {});
        }

        // Pas de cookie = pas connecté, tenter une connexion
        if (!this.authCookie && this.authCookie !== '') {
            await this._connect();
        }

        // Cookie vide = bypass auth
        if (this.authCookie === '') {
            return {};
        }

        return { Cookie: this.authCookie };
    }

    // ─────────────────────────────────────────────
    // REQUÊTE avec retry sur 403
    // ─────────────────────────────────────────────

    async _request(requestFn) {
        const headers = await this._getHeaders();

        try {
            return await requestFn(headers);

        } catch (err) {
            // Session expirée → une seule tentative de reconnexion
            if (err.response?.status === 403) {
                logger.warn(`[Client] 403 reçu (${this.url}), reconnexion...`);
                this.authCookie = undefined;

                // Attendre la reconnexion
                await this._connect();

                const newHeaders = await this._getHeaders();
                return await requestFn(newHeaders);
            }
            throw err;
        }
    }

    // ─────────────────────────────────────────────
    // API
    // ─────────────────────────────────────────────

    async logout() {
        return this._request((headers) =>
            axios
                .post(`${this.url}/api/v2/auth/logout`, null, { headers, timeout: 10000 })
                .then((res) => {
                    this.authCookie = undefined;
                    return res.data;
                })
        );
    }

    async getAppVersion() {
        return this._request((headers) =>
            axios
                .get(`${this.url}/api/v2/app/version`, { headers, timeout: 10000 })
                .then((res) => res.data)
        );
    }

    async getApiVersion() {
        return this._request((headers) =>
            axios
                .get(`${this.url}/api/v2/app/webapiVersion`, { headers, timeout: 10000 })
                .then((res) => res.data)
        );
    }

    async getAppPreferences() {
        return this._request((headers) =>
            axios
                .post(`${this.url}/api/v2/app/preferences`, null, { headers, timeout: 10000 })
                .then((res) => res.data)
        );
    }

    async getTorrentInfos(hashes) {
        return this._request((headers) => {
            const data = hashes
                ? new URLSearchParams({ hashes: hashes.toLowerCase() })
                : null;
            return axios
                .post(`${this.url}/api/v2/torrents/info`, data, { headers, timeout: 10000 })
                .then((res) => res.data);
        });
    }

    async getTorrentContents(hash, indexes) {
        return this._request((headers) => {
            const params = { hash: hash.toLowerCase() };
            if (indexes !== undefined) params.indexes = indexes.toLowerCase();
            return axios
                .post(
                    `${this.url}/api/v2/torrents/files`,
                    new URLSearchParams(params),
                    { headers, timeout: 10000 }
                )
                .then((res) => res.data);
        });
    }

    async getTorrentProperties(hash) {
        return this._request((headers) =>
            axios
                .post(
                    `${this.url}/api/v2/torrents/properties`,
                    new URLSearchParams({ hash: hash.toLowerCase() }),
                    { headers, timeout: 10000 }
                )
                .then((res) => res.data)
        );
    }

    async getTorrentTrackers(hash) {
        return this._request((headers) =>
            axios
                .post(
                    `${this.url}/api/v2/torrents/trackers`,
                    new URLSearchParams({ hash: hash.toLowerCase() }),
                    { headers, timeout: 10000 }
                )
                .then((res) => res.data)
        );
    }

    async getTransferInfo() {
        return this._request((headers) =>
            axios
                .post(`${this.url}/api/v2/transfer/info`, null, { headers, timeout: 10000 })
                .then((res) => res.data)
        );
    }

    async syncMainData() {
        if (this.isMainDataPending === false) {
            this.isMainDataPending = true;

            this.syncRids.mainData = this.syncRids.mainData
                .then((rid) =>
                    this._request((headers) =>
                        axios
                            .post(
                                `${this.url}/api/v2/sync/maindata`,
                                new URLSearchParams({ rid: `${rid}` }),
                                { headers, timeout: 10000 }
                            )
                            .then(({ data }) => {
                                const {
                                    rid: newRid = 0,
                                    full_update = false,
                                    categories = {},
                                    categories_removed = [],
                                    server_state = EMPTY_SERVER_STATE,
                                    tags = [],
                                    tags_removed = [],
                                    torrents = {},
                                    torrents_removed = [],
                                    trackers = {},
                                    trackers_removed = [],
                                } = data;

                                if (full_update) {
                                    this.syncStates.mainData = {
                                        categories,
                                        server_state,
                                        tags,
                                        torrents,
                                        trackers,
                                    };
                                } else {
                                    Object.keys(categories).forEach((k) => {
                                        this.syncStates.mainData.categories[k] = {
                                            ...this.syncStates.mainData.categories[k],
                                            ...categories[k],
                                        };
                                    });
                                    categories_removed.forEach((k) => {
                                        delete this.syncStates.mainData.categories[k];
                                    });

                                    this.syncStates.mainData.tags.push(...tags);
                                    this.syncStates.mainData.tags =
                                        this.syncStates.mainData.tags.filter(
                                            (t) => !tags_removed.includes(t)
                                        );

                                    Object.keys(torrents).forEach((k) => {
                                        this.syncStates.mainData.torrents[k] = {
                                            ...this.syncStates.mainData.torrents[k],
                                            ...torrents[k],
                                        };
                                    });
                                    torrents_removed.forEach((k) => {
                                        delete this.syncStates.mainData.torrents[k];
                                    });

                                    Object.keys(trackers).forEach((k) => {
                                        this.syncStates.mainData.trackers[k] = {
                                            ...this.syncStates.mainData.trackers[k],
                                            ...trackers[k],
                                        };
                                    });
                                    trackers_removed.forEach((k) => {
                                        delete this.syncStates.mainData.trackers[k];
                                    });

                                    this.syncStates.mainData.server_state = {
                                        ...this.syncStates.mainData.server_state,
                                        ...server_state,
                                    };
                                }

                                return newRid;
                            })
                    )
                )
                .finally(() => {
                    this.isMainDataPending = false;
                });
        }

        try {
            await this.syncRids.mainData;
        } catch (e) {
            this.syncRids.mainData = Promise.resolve(0);
            throw e;
        }

        return this.syncStates.mainData;
    }
}

module.exports = Client;
