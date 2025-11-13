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

            $clonedSelect.on('change', function() {
                const value = mQuery(this).val()
                if (value) {
                    const $option = mQuery('option:selected', this);
                    addCondition($option, $clonedSelect);
                    mQuery(this).val('');
                    mQuery(this).trigger('chosen:updated');
                }
            });
        });
    }

    const addCondition = function($option, $select) {
        const label = $option.text();
        const field = $option.val();                      // firstname|email etc.
        const fieldType = $option.data('field-type');     // text|number|datetime etc.
        const fieldObject = $option.data('field-object'); // lead|company|form
        const fieldOperators = $option.data('field-operators');
        const actionId = $select.data('cfa-action-id');
        const $conditionContainer = mQuery('#mauticform_actionConditionsConfig_actionConditions_'+ actionId +'_conditions');
        const filterNum = getConditionCount(actionId);
        const filterIdBase = "mauticform_actionConditionsConfig_actionConditions_" + actionId + "_conditions_" + filterNum + "_";
        const filterBase  = "mauticform[actionConditionsConfig][actionConditions][" + actionId + "][conditions][" + filterNum + "]";

        let prototypeStr = $select.data('cfa-condition-prototype');
        prototypeStr = prototypeStr.replace(/__actionId__/g, actionId);
        prototypeStr = prototypeStr.replace(/__conditionIndex__/g, filterNum);
        prototypeStr = prototypeStr.replace(/__label__/g, label);

        // Convert to DOM
        const $prototype = mQuery(prototypeStr);

        if (filterNum === 0) {
            // First filter, so hide the glue footer
            $prototype.find(".panel-heading .panel-glue").addClass('hide');
        }

        if (fieldObject === 'company') {
            $prototype.find(".object-icon").removeClass('ri-user-6-fill').addClass('ri-building-2-line');
        } else {
            $prototype.find(".object-icon").removeClass('ri-building-2-line').addClass('ri-user-6-fill');
        }
        $prototype.find(".inline-spacer").append(fieldObject);

        // attachEvents($prototype); todo

        $prototype.find("input[name='" + filterBase + "[field]']").val(field);
        $prototype.find("input[name='" + filterBase + "[type]']").val(fieldType);
        $prototype.find("input[name='" + filterBase + "[object]']").val(fieldObject);
        $prototype.appendTo($conditionContainer);

        mQuery('#' + filterIdBase + 'operator').html('');
        mQuery.each(fieldOperators, function (label, value) {
            const newOption = mQuery('<option/>').val(value).text(label);
            newOption.appendTo(mQuery('#' + filterIdBase + 'operator'));
        });

        // todo Convert based on first option in list
        convertLeadFilterInput('#' + filterIdBase + 'operator');

        // todo Reposition if applicable
        // Mautic.updateFilterPositioning(mQuery('#' + filterIdBase + 'glue'));
    }

    const convertLeadFilterInput = function(el) {
        const operatorSelect = mQuery(el);

        // Extract actionId and conditionNum from ID
        // Format: mauticform_actionConditionsConfig_actionConditions_{actionId}_conditions_{conditionNum}_operator
        const regExp = /_actionConditions_([^_]+)_conditions_(\d+)_operator/;
        const matches = regExp.exec(operatorSelect.attr('id'));

        if (!matches) {
            console.error('Could not parse operator select ID:', operatorSelect.attr('id'));
            return;
        }

        const actionId = matches[1];
        const conditionNum = matches[2];
        const fieldBase = 'mauticform_actionConditionsConfig_actionConditions_' + actionId + '_conditions_' + conditionNum;

        const fieldAlias = mQuery('#' + fieldBase + '_field');
        const fieldObject = mQuery('#' + fieldBase + '_object');
        const filterValue = mQuery('#' + fieldBase + '_properties_filter').val();
        const filterId = '#' + fieldBase + '_properties_filter';
        const formId = mQuery('#mauticform_sessionId').val();

        loadFilterForm(formId, actionId, conditionNum, fieldObject.val(), fieldAlias.val(), operatorSelect.val(), function(propertiesFields) {
            const selector = '#' + fieldBase;
            mQuery(selector + '_properties').html(propertiesFields);
            // todo triggerOnPropertiesFormLoadedEvent(selector, filterValue);
        });

        // todo Mautic.setProcessorForFilterValue(filterId, operatorSelect.val());
    };

    const loadFilterForm = function(formId, actionId, conditionNum, fieldObject, fieldAlias, operator, resultHtml, search = null) {
        const url = mQuery('[data-cfa-render-condition-properties]').data('cfa-render-condition-properties');
        mQuery.ajax({
            showLoadingBar: true,
            url: url,
            type: 'GET',
            data: {
                fieldAlias: fieldAlias,
                fieldObject: fieldObject,
                operator: operator,
                actionId: actionId,           // Added
                conditionNum: conditionNum,   // Renamed from filterNum
                search: search,
                formId: formId
            },
            success: function (response) {
                Mautic.stopPageLoadingBar();
                resultHtml(response.viewParameters.form);
            },
            error: function (request, textStatus, errorThrown) {
                Mautic.processAjaxError(request, textStatus, errorThrown);
            }
        });
    };

    const getConditionCount = function(actionId) {
        const $container = mQuery('#mauticform_actionConditionsConfig_actionConditions_'+ actionId +'_conditions');
        return $container.children('.cfa-condition-panel').length;
    };

    Mautic.cfaConvertConditionInput = convertLeadFilterInput;
}(Mautic, mQuery));