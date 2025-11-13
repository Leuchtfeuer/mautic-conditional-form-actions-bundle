(function (Mautic, mQuery){
    Mautic.onFormCfaConditionsBuilder = function() {
        initializeBuilders();
    };

    const initializeBuilders = function() {
        const $actions = mQuery('div[data-cfa-action]');
        const $select = mQuery('select[data-cfa-available-conditions-list]');

        $actions.each(function() {
            const $clonedSelect = $select.clone();
            const actionId = mQuery(this).data('cfa-action');
            $clonedSelect.attr('data-cfa-action-id', actionId);
            mQuery(this).find('div[data-cfa-available-conditions-list-container]').append($clonedSelect);

            const $builder = mQuery('#mauticform_actionConditionsConfig_actionConditions_' + actionId);
            if ($builder.length) {
                mQuery(this).find('div.action-condition-builder-container').html($builder);
            }

        });
    }



}(Mautic, mQuery));