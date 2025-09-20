#include <stdio.h>
#include <windows.h>

int main() {
    HKEY hKey;
    DWORD pid = 32;
    DWORD pidSize = sizeof(pid);

    if (RegOpenKeyEx(HKEY_CURRENT_USER, "Software\\Valve\\Steam\\ActiveProcess", 0, KEY_READ | KEY_WRITE, &hKey) == ERROR_SUCCESS) {
        RegSetValueEx(hKey, "PID", 0, REG_DWORD, (const BYTE*)&pid, pidSize);
        RegCloseKey(hKey);
    }

    printf("Started!\n");

    while (1) {
        Sleep(5000);
    }

    return 0;
}
