#include <windows.h>
#include <tlhelp32.h>
#include <stdio.h>

void KillProcessByName(const char *processName) {
    HANDLE hSnapshot = CreateToolhelp32Snapshot(TH32CS_SNAPPROCESS, 0);
    if (hSnapshot == INVALID_HANDLE_VALUE) return;

    PROCESSENTRY32 pe;
    pe.dwSize = sizeof(PROCESSENTRY32);

    if (Process32First(hSnapshot, &pe)) {
        do {
            if (_stricmp(pe.szExeFile, processName) == 0) {
                HANDLE hProcess = OpenProcess(PROCESS_TERMINATE, FALSE, pe.th32ProcessID);
                if (hProcess) {
                    TerminateProcess(hProcess, 0);
                    CloseHandle(hProcess);
                }
            }
        } while (Process32Next(hSnapshot, &pe));
    }

    CloseHandle(hSnapshot);
}

int main(int argc, char *argv[]) {
    if (argc < 3) {
        return 1;
    }

    KillProcessByName("steam.exe");

    STARTUPINFO si1 = {0};
    PROCESS_INFORMATION pi1 = {0};
    si1.cb = sizeof(si1);

    if (!CreateProcess(NULL, argv[1], NULL, NULL, FALSE, 0, NULL, NULL, &si1, &pi1)) {
        printf("Failed to start fake steam: %s\n", argv[1]);
        return 1;
    }

    STARTUPINFO si2 = {0};
    PROCESS_INFORMATION pi2 = {0};
    si2.cb = sizeof(si2);
    si2.dwFlags |= STARTF_USESTDHANDLES;
    si2.hStdOutput = GetStdHandle(STD_OUTPUT_HANDLE);
    si2.hStdError  = GetStdHandle(STD_ERROR_HANDLE);
    si2.hStdInput  = GetStdHandle(STD_INPUT_HANDLE);

    if (!CreateProcess(NULL, argv[2], NULL, NULL, FALSE, 0, NULL, NULL, &si2, &pi2)) {
        printf("Failed to start game: %s\n", argv[2]);
        TerminateProcess(pi1.hProcess, 0);
        return 1;
    }

    WaitForSingleObject(pi2.hProcess, INFINITE);

    TerminateProcess(pi1.hProcess, 0);

    CloseHandle(pi1.hProcess);
    CloseHandle(pi1.hThread);
    CloseHandle(pi2.hProcess);
    CloseHandle(pi2.hThread);

    return 0;
}
