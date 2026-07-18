document.addEventListener('DOMContentLoaded', function () {
    const tabs = document.querySelectorAll('.telegrarm-tab');
    const panels = document.querySelectorAll('.telegrarm-panel');
    const mappingTextarea = document.getElementById('telegrarm_arm_mapping');
    const discoverButton = document.getElementById('telegrarm-discover-metakeys');
    const selectAllButton = document.getElementById('telegrarm-select-all-metakeys');
    const selectNoneButton = document.getElementById('telegrarm-select-none-metakeys');
    const buildButton = document.getElementById('telegrarm-build-mapping');
    const botTokenInput = document.getElementById('telegram_bot_api_token');
    const statusNode = document.getElementById('telegrarm-metakeys-status');
    const resultsNode = document.getElementById('telegrarm-metakeys-results');
    const testMessageButtons = document.querySelectorAll('.telegrarm-test-message-button');
    const config = window.telegrarmAdmin || {};
    const ajaxUrl = window.ajaxurl || config.ajaxUrl || '';
    const ajaxNonce = config.ajaxNonce || '';
    const testMessageNonce = config.testMessageNonce || '';
    const hasConfiguredBotToken = !!config.hasConfiguredBotToken;
    const existingMapping = config.existingMapping || {};
    const i18n = config.i18n || {};

    function formatCountMessage(singularTemplate, pluralTemplate, count) {
        const template = count === 1 ? singularTemplate : pluralTemplate;
        return String(template || '').replace('%d', String(count));
    }

    function formatMessage(template, value) {
        return String(template || '').replace('%d', String(value));
    }

    function humanizeKey(key) {
        return String(key || '')
            .replace(/([a-z])([A-Z])/g, '$1 $2')
            .replace(/[_-]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim()
            .replace(/\b\w/g, function (match) {
                return match.toUpperCase();
            });
    }

    function setStatus(message, isError) {
        if (!statusNode) {
            return;
        }

        statusNode.textContent = message || '';
        statusNode.style.color = isError ? '#b32d2e' : '';
    }

    function setScopedStatus(node, message, isError) {
        if (!node) {
            return;
        }

        node.textContent = message || '';
        node.style.color = isError ? '#b32d2e' : '';
    }

    function enableRowReordering(tbody) {
        if (!tbody) {
            return;
        }

        let draggedRow = null;
        let dropTargetRow = null;

        function clearDropTarget() {
            if (dropTargetRow) {
                dropTargetRow.classList.remove('is-drop-target');
                dropTargetRow = null;
            }
        }

        tbody.querySelectorAll('tr').forEach(function (row) {
            const handle = row.querySelector('.telegrarm-drag-handle');

            if (!handle) {
                return;
            }

            handle.draggable = true;

            handle.addEventListener('dragstart', function (event) {
                draggedRow = row;
                row.classList.add('is-dragging');

                if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = 'move';
                    event.dataTransfer.setData('text/plain', row.dataset.key || '');
                }
            });

            handle.addEventListener('dragend', function () {
                row.classList.remove('is-dragging');
                draggedRow = null;
                clearDropTarget();
            });

            row.addEventListener('dragover', function (event) {
                if (!draggedRow || draggedRow === row) {
                    return;
                }

                event.preventDefault();

                if (dropTargetRow && dropTargetRow !== row) {
                    dropTargetRow.classList.remove('is-drop-target');
                }

                dropTargetRow = row;
                dropTargetRow.classList.add('is-drop-target');
            });

            row.addEventListener('drop', function (event) {
                const bounds = row.getBoundingClientRect();
                const shouldInsertBefore = event.clientY < bounds.top + (bounds.height / 2);

                event.preventDefault();

                if (!draggedRow || draggedRow === row) {
                    clearDropTarget();
                    return;
                }

                if (shouldInsertBefore) {
                    tbody.insertBefore(draggedRow, row);
                } else {
                    tbody.insertBefore(draggedRow, row.nextSibling);
                }

                clearDropTarget();
            });
        });
    }

    function renderMetakeys(items) {
        if (!resultsNode) {
            return;
        }

        resultsNode.innerHTML = '';
        resultsNode.hidden = false;

        if (!items || !items.length) {
            resultsNode.innerHTML = '<p>' + i18n.noCandidates + '</p>';
            return;
        }

        const table = document.createElement('table');
        table.className = 'telegrarm-metakeys-table';

        const reorderHint = document.createElement('p');
        reorderHint.className = 'description';
        reorderHint.textContent = i18n.reorderHint;
        resultsNode.appendChild(reorderHint);

        const thead = document.createElement('thead');
        const headRow = document.createElement('tr');
        [i18n.reorder, i18n.use, i18n.metaKey, i18n.label, i18n.source].forEach(function (title, index) {
            const th = document.createElement('th');
            if (index === 0) {
                th.className = 'telegrarm-order-cell';
            }
            th.textContent = title;
            headRow.appendChild(th);
        });
        thead.appendChild(headRow);
        table.appendChild(thead);

        const tbody = document.createElement('tbody');

        items.forEach(function (item) {
            const row = document.createElement('tr');
            row.dataset.key = item.key || '';

            const orderCell = document.createElement('td');
            orderCell.className = 'telegrarm-order-cell';
            const dragHandle = document.createElement('span');
            dragHandle.className = 'telegrarm-drag-handle';
            dragHandle.textContent = i18n.reorderMove;
            dragHandle.setAttribute('role', 'button');
            dragHandle.setAttribute('tabindex', '0');
            dragHandle.setAttribute('aria-label', (i18n.reorderMove || 'Move') + ': ' + (item.key || ''));
            orderCell.appendChild(dragHandle);
            row.appendChild(orderCell);

            const checkCell = document.createElement('td');
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.checked = !!item.isSelected;
            checkbox.setAttribute('aria-label', item.key || '');
            checkCell.appendChild(checkbox);
            row.appendChild(checkCell);

            const keyCell = document.createElement('td');
            const keyCode = document.createElement('code');
            keyCode.textContent = item.key || '';
            keyCell.appendChild(keyCode);
            row.appendChild(keyCell);

            const labelCell = document.createElement('td');
            const labelInput = document.createElement('input');
            labelInput.type = 'text';
            labelInput.className = 'regular-text telegrarm-input';
            labelInput.value = item.label || humanizeKey(item.key);
            labelCell.appendChild(labelInput);
            row.appendChild(labelCell);

            const sourceCell = document.createElement('td');
            const sourceBadge = document.createElement('span');
            sourceBadge.className = 'telegrarm-metakey-source';
            if (item.source === 'form_field') {
                sourceBadge.textContent = i18n.formField;
            } else if (item.source === 'preset') {
                sourceBadge.textContent = i18n.preset;
            } else if (item.source === 'common') {
                sourceBadge.textContent = i18n.builtIn;
            } else if (item.source === 'usermeta') {
                sourceBadge.textContent = i18n.usermeta;
            } else {
                sourceBadge.textContent = i18n.detected;
            }
            sourceCell.appendChild(sourceBadge);
            row.appendChild(sourceCell);

            tbody.appendChild(row);
        });

        table.appendChild(tbody);
        resultsNode.appendChild(table);
        enableRowReordering(tbody);
    }

    function collectSelectedMapping() {
        const mapping = {};

        if (!resultsNode) {
            return mapping;
        }

        resultsNode.querySelectorAll('tbody tr').forEach(function (row) {
            const checkbox = row.querySelector('input[type="checkbox"]');

            if (!checkbox || !checkbox.checked) {
                return;
            }

            const key = row.dataset.key || '';
            const labelInput = row.querySelector('input[type="text"]');
            const label = labelInput && labelInput.value ? labelInput.value.trim() : humanizeKey(key);

            if (key) {
                mapping[key] = label;
            }
        });

        return mapping;
    }

    function setAllCheckboxes(checked) {
        if (!resultsNode) {
            return;
        }

        resultsNode.querySelectorAll('tbody input[type="checkbox"]').forEach(function (checkbox) {
            checkbox.checked = checked;
        });
    }

    function buildMappingJson() {
        if (!mappingTextarea) {
            return;
        }

        const mapping = collectSelectedMapping();
        const keys = Object.keys(mapping);

        if (!keys.length) {
            setStatus(i18n.selectAtLeastOne, true);
            return;
        }

        mappingTextarea.value = JSON.stringify(mapping, null, 2);
        setStatus(formatCountMessage(i18n.builtJsonSingular, i18n.builtJsonPlural, keys.length));
        mappingTextarea.focus();
    }

    if (discoverButton && resultsNode) {
        discoverButton.addEventListener('click', function () {
            setStatus(i18n.discovering);
            discoverButton.disabled = true;

            const body = new URLSearchParams();
            body.set('action', 'telegrarm_discover_arm_metakeys');
            body.set('_ajax_nonce', ajaxNonce);
            body.set('refresh', '1');

            fetch(ajaxUrl, {
                credentials: 'same-origin',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: body.toString(),
            })
                .then(function (response) {
                    return response.json()
                        .catch(function () {
                            throw new Error(formatMessage(i18n.requestFailed, response.status));
                        })
                        .then(function (payload) {
                            if (!response.ok) {
                                const message = payload && payload.data && payload.data.message ? payload.data.message : formatMessage(i18n.requestFailed, response.status);
                                throw new Error(message);
                            }

                            return payload;
                        });
                })
                .then(function (payload) {
                    if (!payload || !payload.success || !payload.data || !Array.isArray(payload.data.items)) {
                        throw new Error(i18n.unexpectedResponse);
                    }

                    renderMetakeys(payload.data.items);
                    setStatus(formatCountMessage(i18n.discoveredCountSingular, i18n.discoveredCountPlural, payload.data.count));
                })
                .catch(function (error) {
                    resultsNode.hidden = true;
                    resultsNode.innerHTML = '';
                    setStatus(error.message || i18n.unknownDiscoveryError, true);
                })
                .finally(function () {
                    discoverButton.disabled = false;
                });
        });
    }

    if (selectAllButton) {
        selectAllButton.addEventListener('click', function () {
            setAllCheckboxes(true);
        });
    }

    if (selectNoneButton) {
        selectNoneButton.addEventListener('click', function () {
            setAllCheckboxes(false);
        });
    }

    if (buildButton) {
        buildButton.addEventListener('click', function () {
            buildMappingJson();
        });
    }

    if (resultsNode) {
        resultsNode.addEventListener('input', function () {
            if (statusNode && statusNode.textContent) {
                setStatus('');
            }
        });
    }

    if (resultsNode && Object.keys(existingMapping).length > 0) {
        setStatus(formatCountMessage(i18n.currentMappingCountSingular, i18n.currentMappingCountPlural, Object.keys(existingMapping).length));
    }

    if (testMessageButtons.length) {
        testMessageButtons.forEach(function (button) {
            const testMessageContainer = button.closest('.telegrarm-test-message');
            const statusTarget = testMessageContainer ? testMessageContainer.querySelector('.telegrarm-test-message-status') : null;
            const channelInput = document.getElementById(button.getAttribute('data-channel-input') || '');

            if (channelInput) {
                channelInput.addEventListener('input', function () {
                    setScopedStatus(statusTarget, '', false);
                });
            }

            button.addEventListener('click', function () {
                const botToken = botTokenInput && botTokenInput.value ? botTokenInput.value.trim() : '';
                const channelId = channelInput && channelInput.value ? channelInput.value.trim() : '';
                const previousLabel = button.textContent;

                if (!botToken && !hasConfiguredBotToken) {
                    setScopedStatus(statusTarget, i18n.testMessageMissingBotToken, true);

                    if (botTokenInput) {
                        botTokenInput.focus();
                    }

                    return;
                }

                if (!channelId) {
                    setScopedStatus(statusTarget, i18n.testMessageMissingChannel, true);

                    if (channelInput) {
                        channelInput.focus();
                    }

                    return;
                }

                setScopedStatus(statusTarget, i18n.sendingTestMessage, false);
                button.disabled = true;
                button.textContent = i18n.sendingTestMessage;

                const body = new URLSearchParams();
                body.set('action', 'telegrarm_send_test_message');
                body.set('_ajax_nonce', testMessageNonce);
                body.set('bot_token', botToken);
                body.set('channel_id', channelId);
                body.set('target', button.getAttribute('data-target') || '');

                fetch(ajaxUrl, {
                    credentials: 'same-origin',
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    },
                    body: body.toString(),
                })
                    .then(function (response) {
                        return response.json()
                            .catch(function () {
                                throw new Error(formatMessage(i18n.requestFailed, response.status));
                            })
                            .then(function (payload) {
                                if (!response.ok || !payload || !payload.success) {
                                    const message = payload && payload.data && payload.data.message ? payload.data.message : formatMessage(i18n.requestFailed, response.status);
                                    throw new Error(message);
                                }

                                return payload;
                            });
                    })
                    .then(function (payload) {
                        const message = payload && payload.data && payload.data.message ? payload.data.message : i18n.unknownTestMessageError;
                        setScopedStatus(statusTarget, message, false);
                    })
                    .catch(function (error) {
                        setScopedStatus(statusTarget, error.message || i18n.unknownTestMessageError, true);
                    })
                    .finally(function () {
                        button.disabled = false;
                        button.textContent = previousLabel;
                    });
            });
        });
    }

    function activateTab(targetPanel, updateHash) {
        let hasMatch = false;

        tabs.forEach(function (item) {
            const isTarget = item.getAttribute('data-panel') === targetPanel;
            item.classList.toggle('nav-tab-active', isTarget);
            item.setAttribute('aria-selected', isTarget ? 'true' : 'false');
            hasMatch = hasMatch || isTarget;
        });

        panels.forEach(function (panel) {
            const isTarget = panel.getAttribute('data-panel') === targetPanel;
            panel.classList.toggle('is-active', isTarget);
            panel.hidden = !isTarget;
        });

        if (hasMatch && updateHash) {
            window.location.hash = targetPanel;
        }
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function (event) {
            event.preventDefault();
            activateTab(tab.getAttribute('data-panel'), true);
        });
    });

    const initialPanel = window.location.hash ? window.location.hash.replace('#', '') : 'bot';
    activateTab(initialPanel, false);

    window.addEventListener('hashchange', function () {
        const hashPanel = window.location.hash ? window.location.hash.replace('#', '') : 'bot';
        activateTab(hashPanel, false);
    });
});
