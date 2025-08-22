import { ref, onMounted } from 'vue';

export function useThemeMode() {
  const defaultThemeMode = 'light';
  const themeMode = ref(defaultThemeMode);

  const setThemeMode = (mode) => {
    document.documentElement.setAttribute('data-bs-theme', mode);
    localStorage.setItem('data-bs-theme', mode);
    themeMode.value = mode;
  };

  onMounted(() => {
    if (document.documentElement) {
      if (document.documentElement.hasAttribute('data-bs-theme-mode')) {
        themeMode.value = document.documentElement.getAttribute('data-bs-theme-mode');
      } else {
        if (localStorage.getItem('data-bs-theme') !== null) {
          themeMode.value = localStorage.getItem('data-bs-theme');
        } else {
          themeMode.value = defaultThemeMode;
        }
      }

      setThemeMode(themeMode.value);
    }
  });

  return {
    themeMode,
    setThemeMode
  };
}
