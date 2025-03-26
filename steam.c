#include <stdio.h>
#include <windows.h>

int main() {
    HKEY hKey;
    DWORD pid, pidSize = sizeof(pid);

    if (RegOpenKeyEx(HKEY_CURRENT_USER, "Software\\Valve\\Steam\\ActiveProcess", 0, KEY_READ | KEY_WRITE, &hKey) == ERROR_SUCCESS) {
        RegQueryValueEx(hKey, "PID", NULL, NULL, (LPBYTE)&pid, &pidSize);

        getchar();

        RegSetValueEx(hKey, "PID", 0, REG_DWORD, (const BYTE*)&pid, sizeof(pid));
        RegCloseKey(hKey);
    }
    return 0;
}
