export default function console_log(message) {
  console.log(message);
}

const count = sessionStorage.getItem('import_test_counter') || 0;
sessionStorage.setItem('import_test_counter', parseInt(count) + 1);
