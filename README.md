[English](https://github.com/ZzEdovec/onlinefix-linux/blob/main/README.md) | [Русский](https://github.com/ZzEdovec/onlinefix-linux/blob/main/README_ru.md)

![OFME window](https://zzedovec.github.io/images/ofmeBanner.png)
# OnlineFix Linux Launcher

**A simple and convenient launcher for running games from ****[online-fix.me](https://online-fix.me)**** on Linux**

## ✨ Features

- Launch games without the need to manually set `WINEDLLOVERRIDES` and other configurations
- Automatically fetch game covers from Steam
- Retrieve game icons
- Create desktop and application menu shortcuts for games
- Bypassing the "Steam is not running" error in some fixes

## ❕ Compatibility

Most online fixes from online-fix.me are currently supported. Partial support is available for fixes from freetp.org *(it is highly recommended to download games from online-fix.me if available, rather than from FreeTP)*.
Fixes that include custom launchers (e.g., Phasmophobia) have not been tested yet.

Work-in-progres:
- Epic Games fixes

## 📦 Dependencies

Before using the launcher, ensure that the following packages are installed:

- `protontricks`
- `ffmpeg`
- `steam`

‼️ They must be installed as regular packages. Flatpak and Snap versions **are not supported and will not be!** If you use them, the launcher will not work correctly — and this is not the developer's fault.

### Installing protontricks:

#### SteamOS / Steam Deck:

1. Disable the read-only mode for the file system:
   ```bash
   sudo steamos-readonly disable
   ```
2. Edit `/etc/pacman.conf` and set `SigLevel = TrustAll`
   - **Warning:** Using `TrustAll` disables package signature verification, which can pose security risks. However, without this change, `pacman` does not function properly on SteamOS.
   - You can use `nano` or `kate` to edit the file:
     ```bash
     sudo nano /etc/pacman.conf
     ```
     or
     ```bash
     sudo kate /etc/pacman.conf
     ```
3. Enable the **Chaotic AUR** repository by following the [official instructions](https://aur.chaotic.cx/docs)
4. Install the protontricks:
   ```bash
   sudo pacman -Sy protontricks-git
   ```
5. After installation, it is recommended to re-enable the read-only mode:
   ```bash
   sudo steamos-readonly enable
   ```

#### Ubuntu/Debian and derivatives:

```bash
sudo apt install python3-pip python3-setuptools python3-venv pipx winetricks
pipx install protontricks
```
*You **must** install `protontricks` using `pipx` **even if `protontricks` is installed via the system package manager**, as the version from the system repositories **does not work!***

#### Fedora:

```bash
sudo dnf install protontricks
```

#### Arch Linux and derivatives:


If you do not have `yay` installed, first install it:

```bash
sudo pacman --noconfirm -S git
git clone https://aur.archlinux.org/yay-bin.git
cd yay-bin
makepkg --noconfirm -si
cd ..
rm -rf yay-bin
```

Then install protontricks using:

```bash
yay --noconfirm -S protontricks-git
```

#### Solus:

```bash
sudo eopkg install protontricks
```

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
