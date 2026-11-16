/* ==========================================================================
   Simple CDN 后台 - 统一脚本 (admin.js)
   功能：多重缓存管理、表单确认、AJAX 请求辅助、暗色模式切换
   ========================================================================== */

(function () {
    'use strict';

    // ============ 配置 ============
    const CONFIG = {
        cacheKeys: {
            lastVisit: 'simple_cdn_last_visit',
            visitCount: 'simple_cdn_visit_count',
            cacheStats: 'simple_cdn_cache_stats',
        },
        apiEndpoints: {
            purge: 'action.php?do=purge_all',
            warm: 'action.php?do=cache_warm',
            stats: 'stats.php',
        },
    };

    // ============ 多重缓存管理 ============
    const MultiCache = {
        /**
         * 第一层：浏览器 localStorage 缓存
         */
        setLocal(key, value, ttlSeconds = 300) {
            try {
                const item = {
                    value,
                    expiry: Date.now() + ttlSeconds * 1000,
                };
                localStorage.setItem(key, JSON.stringify(item));
            } catch (e) {
                console.warn('[SimpleCDN] localStorage 写入失败:', e);
            }
        },

        getLocal(key) {
            try {
                const raw = localStorage.getItem(key);
                if (!raw) return null;
                const item = JSON.parse(raw);
                if (Date.now() > item.expiry) {
                    localStorage.removeItem(key);
                    return null;
                }
                return item.value;
            } catch (e) {
                console.warn('[SimpleCDN] localStorage 读取失败:', e);
                return null;
            }
        },

        removeLocal(key) {
            try {
                localStorage.removeItem(key);
            } catch (e) {
                console.warn('[SimpleCDN] localStorage 删除失败:', e);
            }
        },

        /**
         * 第二层：sessionStorage 缓存（会话级）
         */
        setSession(key, value) {
            try {
                sessionStorage.setItem(key, JSON.stringify({ value, time: Date.now() }));
            } catch (e) {
                console.warn('[SimpleCDN] sessionStorage 写入失败:', e);
            }
        },

        getSession(key) {
            try {
                const raw = sessionStorage.getItem(key);
                if (!raw) return null;
                return JSON.parse(raw).value;
            } catch (e) {
                return null;
            }
        },

        /**
         * 第三层：Cookie 缓存（跨页面持久化，小数据）
         */
        setCookie(key, value, days = 7) {
            try {
                const expires = new Date(Date.now() + days * 86400000).toUTCString();
                document.cookie = `${encodeURIComponent(key)}=${encodeURIComponent(value)};expires=${expires};path=/;SameSite=Lax`;
            } catch (e) {
                console.warn('[SimpleCDN] cookie 写入失败:', e);
            }
        },

        getCookie(key) {
            try {
                const name = encodeURIComponent(key) + '=';
                const cookies = document.cookie.split(';');
                for (let c of cookies) {
                    c = c.trim();
                    if (c.startsWith(name)) {
                        return decodeURIComponent(c.substring(name.length));
                    }
                }
                return null;
            } catch (e) {
                return null;
            }
        },

        /**
         * 多重缓存读取：localStorage -> sessionStorage -> Cookie -> null
         */
        getMulti(key) {
            return this.getLocal(key) || this.getSession(key) || this.getCookie(key);
        },

        /**
         * 多重缓存写入：同时写入所有层级
         */
        setMulti(key, value, opts = {}) {
            this.setLocal(key, value, opts.ttlSeconds || 300);
            this.setSession(key, value);
            this.setCookie(key, value, opts.days || 7);
        },

        /**
         * 清除所有层级缓存
         */
        clearMulti(key) {
            this.removeLocal(key);
            try { sessionStorage.removeItem(key); } catch (e) {}
            this.setCookie(key, '', -1);
        },
    };

    // ============ 访问统计（多重缓存示例） ============
    function initVisitStats() {
        const countKey = CONFIG.cacheKeys.visitCount;
        let count = MultiCache.getMulti(countKey);
        if (count === null) count = 0;
        count++;
        MultiCache.setMulti(countKey, count, { ttlSeconds: 86400, days: 30 });

        const lastVisitKey = CONFIG.cacheKeys.lastVisit;
        MultiCache.setMulti(lastVisitKey, new Date().toISOString(), { ttlSeconds: 86400 * 30, days: 30 });

        // 如果页面有显示访问次数的元素，更新它
        const el = document.getElementById('visit-count');
        if (el) el.textContent = count;
    }

    // ============ 表单确认辅助 ============
    function confirmAction(message, callback) {
        if (confirm(message)) {
            callback();
        }
    }

    // 绑定所有带 data-confirm 的按钮
    function bindConfirmButtons() {
        document.querySelectorAll('[data-confirm]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                const msg = btn.getAttribute('data-confirm');
                if (!confirm(msg)) {
                    e.preventDefault();
                }
            });
        });
    }

    // ============ AJAX 请求辅助 ============
    async function apiRequest(url, options = {}) {
        const showLoading = options.showLoading !== false;
        const loader = showLoading ? showLoadingOverlay() : null;

        try {
            const response = await fetch(url, {
                method: options.method || 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...options.headers,
                },
                ...(options.body && { body: options.body }),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                return await response.json();
            }
            return await response.text();
        } finally {
            if (loader) hideLoadingOverlay();
        }
    }

    // ============ 加载遮罩 ============
    let loadingOverlay = null;

    function showLoadingOverlay() {
        if (loadingOverlay) return loadingOverlay;
        loadingOverlay = document.createElement('div');
        loadingOverlay.className = 'loading-overlay';
        loadingOverlay.innerHTML = `
            <div class="loading-spinner" aria-label="加载中"></div>
        `;
        document.body.appendChild(loadingOverlay);
        return loadingOverlay;
    }

    function hideLoadingOverlay() {
        if (loadingOverlay) {
            loadingOverlay.remove();
            loadingOverlay = null;
        }
    }

    // ============ 暗色模式切换 ============
    function initDarkMode() {
        const saved = localStorage.getItem('simple_cdn_dark_mode');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const isDark = saved === '1' || (saved === null && prefersDark);

        if (isDark) {
            document.documentElement.classList.add('dark');
        }

        // 监听系统主题变化（如果用户未手动设置）
        if (saved === null) {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                if (localStorage.getItem('simple_cdn_dark_mode') === null) {
                    if (e.matches) {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                }
            });
        }
    }

    function toggleDarkMode() {
        const isDark = document.documentElement.classList.toggle('dark');
        localStorage.setItem('simple_cdn_dark_mode', isDark ? '1' : '0');
        return isDark;
    }

    // ============ 缓存操作辅助 ============
    async function purgeAllNodes() {
        if (!confirm('确定要清理所有节点的缓存吗？主站和全部启用的节点都会被清理。')) {
            return;
        }

        try {
            const result = await apiRequest(CONFIG.apiEndpoints.purge, {
                method: 'POST',
                showLoading: true,
            });

            if (result && result.success) {
                alert(
                    `清理完成：\n主站删除 ${result.deleted || 0} 个文件\n节点成功 ${result.nodes_notified || 0} 个，失败 ${result.nodes_failed || 0} 个`
                );
                // 刷新页面以更新统计
                window.location.reload();
            } else {
                alert('清理失败：' + (result && result.error ? result.error : '未知错误'));
            }
        } catch (e) {
            alert('请求失败：' + e.message);
        }
    }

    async function warmCache() {
        if (!confirm('确定要立即对所有在线节点预热所有配置的URL吗？')) {
            return;
        }

        try {
            const result = await apiRequest(CONFIG.apiEndpoints.warm, {
                method: 'POST',
                showLoading: true,
            });

            if (result && result.success !== false) {
                alert(
                    `预热完成：\n总请求 ${result.total || 0} 个\n成功 ${result.success || 0} 个，失败 ${result.failed || 0} 个`
                );
            } else {
                alert('预热失败：' + (result && result.error ? result.error : '未知错误'));
            }
        } catch (e) {
            alert('请求失败：' + e.message);
        }
    }

    // ============ 初始化 ============
    function init() {
        initVisitStats();
        bindConfirmButtons();
        initDarkMode();

        // 将全局函数挂载到 window，供 HTML 中的 onclick 调用
        window.SimpleCDN = {
            purgeAllNodes,
            warmCache,
            toggleDarkMode,
            confirmAction,
            apiRequest,
            MultiCache,
        };

        console.log('[SimpleCDN] admin.js 已加载，多重缓存模块就绪');
    }

    // DOM 加载完成后初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
