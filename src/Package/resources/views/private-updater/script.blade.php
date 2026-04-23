<script>
    (function () {
        if (window.EntomaiPrivatePluginUpdaterLoaded) {
            return;
        }

        window.EntomaiPrivatePluginUpdaterLoaded = true;

        const checkUrl = @json($checkUrl);
        const updateUrlTemplate = @json($updateUrlTemplate);
        const checkTimeKey = 'entomai_private_plugin_update_check_time';
        const checkDataKey = 'entomai_private_plugin_update_data';
        const cacheTtl = 15 * 60 * 1000;

        const shouldCheck = function () {
            const lastCheckTime = localStorage.getItem(checkTimeKey);

            return ! lastCheckTime || Date.now() - parseInt(lastCheckTime) > cacheTtl;
        };

        const updateUrl = function (plugin) {
            return updateUrlTemplate.replace('__plugin__', encodeURIComponent(plugin));
        };

        const setButtonLoading = function ($button, loading) {
            if (loading && window.Botble && Botble.showButtonLoading) {
                Botble.showButtonLoading($button);

                return;
            }

            if (! loading && window.Botble && Botble.hideButtonLoading) {
                Botble.hideButtonLoading($button);
            }
        };

        const updateAvailableCount = function () {
            const count = $('.plugin-item').filter(function () {
                return !! $(this).data('available-for-updates');
            }).length;

            $('[data-bb-toggle="plugins-count"][data-status="updates-available"]').text(count);
        };

        const findPluginCard = function (plugin) {
            const $statusButton = $('.btn-trigger-change-status[data-plugin="' + plugin + '"]');

            if ($statusButton.length) {
                return $statusButton.closest('.plugin-item');
            }

            const $updateButton = $('button[data-name="' + plugin + '"]');

            return $updateButton.length ? $updateButton.closest('.plugin-item') : $();
        };

        const ensureUpdateButton = function ($pluginCard, update) {
            let $button = $pluginCard.find('button[data-name="' + update.plugin + '"]').first();

            if (! $button.length) {
                const $buttonList = $pluginCard.find('.btn-list').first();

                if (! $buttonList.length) {
                    return $();
                }

                $button = $('<button type="button" class="btn btn-success btn-sm"></button>');
                $button.attr('data-name', update.plugin);
                $buttonList.prepend($button);
            }

            $button
                .removeClass('btn-trigger-update-plugin')
                .addClass('btn-trigger-entomai-update-plugin')
                .removeAttr('data-update-url')
                .attr('data-entomai-update-url', updateUrl(update.plugin))
                .attr('data-entomai-update-version', update.version || '')
                .attr('data-entomai-update-id', update.update_id || '')
                .attr('title', update.summary || update.message || '')
                .text(update.version ? 'Update to ' + update.version : 'Update')
                .show();

            return $button;
        };

        const processUpdateData = function (data) {
            if (! data) {
                return;
            }

            Object.keys(data).forEach(function (key) {
                const update = data[key];
                const $pluginCard = findPluginCard(update.plugin || key);

                if (! $pluginCard.length || ! update.has_update) {
                    return;
                }

                ensureUpdateButton($pluginCard, update);
                $pluginCard.data('available-for-updates', true).attr('data-available-for-updates', '1');
            });

            updateAvailableCount();
        };

        const checkUpdates = function () {
            const cached = localStorage.getItem(checkDataKey);

            if (cached && ! shouldCheck()) {
                try {
                    processUpdateData(JSON.parse(cached));

                    return;
                } catch (e) {
                    localStorage.removeItem(checkDataKey);
                }
            }

            if (! shouldCheck()) {
                return;
            }

            $httpClient
                .make()
                .post(checkUrl)
                .then(function ({ data }) {
                    localStorage.setItem(checkTimeKey, Date.now().toString());

                    if (! data.data) {
                        localStorage.removeItem(checkDataKey);

                        return;
                    }

                    localStorage.setItem(checkDataKey, JSON.stringify(data.data));
                    processUpdateData(data.data);
                })
                .catch(function () {
                    localStorage.setItem(checkTimeKey, Date.now().toString());
                });
        };

        $(document).on('click', '.btn-trigger-entomai-update-plugin', function (event) {
            event.preventDefault();

            const $button = $(event.currentTarget);

            $button.prop('disabled', true);
            setButtonLoading($button, true);

            $httpClient
                .make()
                .post($button.attr('data-entomai-update-url'), {
                    version: $button.attr('data-entomai-update-version'),
                    update_id: $button.attr('data-entomai-update-id'),
                })
                .then(function ({ data }) {
                    if (data.error) {
                        Botble.showError(data.message || 'Private plugin update failed.');

                        return;
                    }

                    Botble.showSuccess(data.message || 'Plugin updated successfully.');

                    localStorage.removeItem(checkTimeKey);
                    localStorage.removeItem(checkDataKey);
                    localStorage.removeItem('plugin_update_check_time');
                    localStorage.removeItem('plugin_update_data');

                    setTimeout(function () {
                        window.location.reload();
                    }, 2000);
                })
                .finally(function () {
                    setButtonLoading($button, false);
                    $button.prop('disabled', false);
                });
        });

        $(function () {
            checkUpdates();
        });
    })();
</script>
