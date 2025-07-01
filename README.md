[English](https://github.com/ZzEdovec/onlinefix-linux/blob/main/README.md) | [Русский](https://github.com/ZzEdovec/onlinefix-linux/blob/main/README_ru.md)

![OFME window](https://zzedovec.github.io/images/ofmeBanner.png)
# OnlineFix Linux Launcher

**A simple and convenient launcher for running games with community multiplayer fixes on Linux**

## ✨ Features

- Launch games without the need to manually set `WINEDLLOVERRIDES` and other configurations
- Automatically fetch game covers from Steam
- Retrieve game icons
- Create desktop and application menu shortcuts for games
- Bypassing the "Steam is not running" error in some fixes

## ❕ Compatibility

Most online fixes are currently supported.
Fixes that include custom launchers have not been tested yet.

## 📦 Dependencies

Before using the launcher, ensure that the following packages are installed:

- `ffmpeg`
- `steam`

‼️ They must be installed as regular packages. Flatpak and Snap versions **are not supported and will not be!** If you use them, the launcher will not work correctly — and this is not the developer's fault.

## ⬇️ Installation

You can download a precompiled version with an installer from the [Releases](https://github.com/ZzEdovec/onlinefix-linux/releases) section.

## 🏗 Building from Source

To build the launcher, you will need [DevelNext](https://develnext.org):

1. Open DevelNext
2. Clone the repository to any folder on your disk:
   ```bash
   git clone https://github.com/ZzEdovec/onlinefix-linux
   ```
3. Open the `.dnproject` file in DevelNext
4. Click the build button at the top

After building, you will obtain the executable launcher file.
