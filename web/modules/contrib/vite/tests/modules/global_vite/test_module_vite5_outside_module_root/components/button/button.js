((Drupal, once) => {
  Drupal.behaviors.test_module_vite5_outside_module_root_components_button = {
    attach(context) {
      once(context, 'button', (button) => {
        button.addEventListener('click', () => {
          alert('Click!');
        });
      });
    },
  };
})(Drupal, once);
