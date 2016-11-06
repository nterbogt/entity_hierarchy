(function ($) {
  $(document).ready(function() {
    $('.entity_hierarchy-menu-link').each(function() {
      var self = this;

      $('.entity_hierarchy-parent-delete', self).each(function() {
        if ($(this).attr('checked')) {
          // Set display none instead of using hide(), because hide() doesn't work when parent is hidden.
          $('.form-item', self).not($(this).parents()).css('display', 'none');
        }
      });
      $('.entity_hierarchy-parent-delete', self).bind('click', function () {
        if ($(this).attr('checked')) {
          $('.form-item', self).not($(this).parents()).slideUp('fast');
        }
        else {
          $('.form-item', self).not($(this).parents()).slideDown('fast');
        }
      });

      if (!$('.entity_hierarchy-menu-enable', self).attr('checked')) {
        // Set display none instead of using hide(), because hide() doesn't work when parent is hidden.
        $('.entity_hierarchy-menu-settings', self).css('display', 'none');
      }
      $('.entity_hierarchy-menu-enable', self).bind('click', function() {
        if ($(this).attr('checked')) {
          $('.entity_hierarchy-menu-settings', self).slideDown('fast');
        }
        else {
          $('.entity_hierarchy-menu-settings', self).slideUp('fast');
        }
      });

      if (!$('.entity_hierarchy-menu-customize', self).attr('checked')) {
        // Set display none instead of using hide(), because hide() doesn't work when parent is hidden.
        $('.entity_hierarchy-menu-title', self).css('display', 'none');
      }
      $('.entity_hierarchy-menu-customize', self).bind('click', function() {
        if ($(this).attr('checked')) {
          $('.entity_hierarchy-menu-title', self).slideDown('fast');
        }
        else {
          $('.entity_hierarchy-menu-title', self).slideUp('fast');
        }
      });

      if ($('.entity_hierarchy-parent-selector', self).length && $('.entity_hierarchy-parent-selector', self).attr("selectedIndex") != 0) {
        // Set display none instead of using hide(), because hide() doesn't work when parent is hidden.
        $('.entity_hierarchy-menu-name', self).css('display', 'none');
      }
      $('.entity_hierarchy-parent-selector', self).change(function() {
        if ($(this).attr("selectedIndex") == 0) {
          $('.entity_hierarchy-menu-name', self).slideDown('fast');
        }
        else {
          $('.entity_hierarchy-menu-name', self).slideUp('fast');
        }
      });
    });
  });
})(jQuery);

