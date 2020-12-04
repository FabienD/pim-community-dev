'use strict';
/**
 * Textarea field
 *
 * @author    Julien Sanchez <julien@akeneo.com>
 * @author    Filips Alpe <filips@akeneo.com>
 * @copyright 2015 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
define(['pim/field', 'underscore', 'pim/template/product/field/textarea'], function (Field, _, fieldTemplate) {
  return Field.extend({
    fieldTemplate: _.template(fieldTemplate),
    events: {
      'change .field-input:first textarea': 'updateModel',
    },

    /**
     * @inheritDoc
     */
    renderInput: function (context) {
      return this.fieldTemplate(context);
    },

    /**
     * @inheritDoc
     */
    updateModel: function () {
      var data = this.$('.field-input:first textarea:first').val();
      data = '' === data ? this.attribute.empty_value : data;

      this.setCurrentValue(data);
    },

    /**
     * @inheritDoc
     */
    setFocus: function () {
      this.$('.field-input:first textarea').focus();
    },
  });
});
