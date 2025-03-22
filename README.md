![OFME window](https://zzedovec.github.io/images/ofmeBanner.png)
# OnlineFix Linux Launcher

**A simple and convenient launcher for running games from ****[online-fix.me](https://online-fix.me)**** on Linux**

## ✨ Features

- Launch games without the need to manually set `WINEDLLOVERRIDES` and other configurations
- Automatically fetch game covers from Steam
- Retrieve game icons
- Create desktop and application menu shortcuts for games

## ❕ Compatibility

Currently, most online fixes from online-fix.me are supported.
Fixes that include custom launchers (e.g., Phasmophobia) have not been tested yet.

Work-in-progres:
- Epic Games fixes
- Steam fixes from freetp.org

## 📦 Dependencies

Before using the launcher, ensure that the following packages are installed:

- `protontricks`
- `ffmpeg`
- `7zip`

### Installing Dependencies:

#### Ubuntu and derivatives:

```bash
sudo apt install protontricks ffmpeg p7zip-full
```

#### Fedora:

```bash
sudo dnf install protontricks ffmpeg p7zip
```

#### Arch Linux and derivatives:


If you do not have `yay` installed, first install it:

```bash
git clone https://aur.archlinux.org/yay-bin.git
cd yay-bin
makepkg -si
cd ..
rm -rf yay-bin
```

Then install all dependencies using:

```bash
yay -S --noconfirm protontricks ffmpeg 7zip
```

#### Solus:

```bash
sudo eopkg install protontricks ffmpeg p7zip
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
