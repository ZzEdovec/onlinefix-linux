
[English](https://github.com/ZzEdovec/onlinefix-linux/blob/main/README.md) | [Русский](https://github.com/ZzEdovec/onlinefix-linux/blob/main/README_ru.md)

![Окно OFME](https://zzedovec.github.io/images/ofmeBanner.png)
# OnlineFix Linux Launcher

**Простой и удобный лаунчер для запуска игр с ****[online-fix.me](https://online-fix.me)**** на Linux**

## ✨ Возможности

- Запуск игр без необходимости вручную настраивать `WINEDLLOVERRIDES` и другие параметры
- Автоматическое получение обложек игр из Steam
- Автоматическое извлечение иконок из игр
- Создание ярлыков на рабочем столе и в меню приложений для игр

## ❕ Совместимость

В настоящее время поддерживаются большинство онлайн-фиксов с online-fix.me. Частично поддерживаются фиксы с freetp.org *(крайне рекомендуется при наличии игры на online-fix.me скачивать её именно оттуда, а не с FreeTP)*
Фиксы, включающие пользовательские лаунчеры (например, Phasmophobia), пока не тестировались.

В разработке:
- Фиксы для Epic Games

## 📦 Зависимости

Перед использованием лаунчера убедитесь, что установлены следующие пакеты:

- `protontricks`
- `ffmpeg`
- `steam`

‼️ Они должны быть установлены как нормальные пакеты. Flatpak и Snap версии **не поддерживаются и не будут!** В случае их использования лаунчер не будет правильно работать - и это не вина его разработчика.

### Установка protontricks:

#### SteamOS / Steam Deck:

```bash
sudo steamos-readonly disable
sudo pacman-key --init
sudo pacman-key --populate archlinux
sudo pacman -Sy python-pipx winetricks
pipx install protontricks
sudo steamos-readonly enable
```

#### Ubuntu/Debian и производные:

```bash
sudo apt install python3-pip python3-setuptools python3-venv pipx winetricks
pipx install protontricks
```
*Вы **должны** установить `protontricks` при помощи `pipx` **даже если `protontricks` установлен через системный пакетный менеджер**, т.к. версия из системных репозиториев **не работает!***

#### Fedora:

```bash
sudo dnf install protontricks
```

#### Arch Linux и производные:

Если у вас не установлен `yay`, сначала установите его:

```bash
sudo pacman --noconfirm -S git
git clone https://aur.archlinux.org/yay-bin.git
cd yay-bin
makepkg --noconfirm -si
cd ..
rm -rf yay-bin
```

Затем установите protontricks с помощью:

```bash
yay --noconfirm -S protontricks-git
```

#### Solus:

```bash
sudo eopkg install protontricks
```

## ⬇️ Установка

Вы можете скачать готовую версию с установщиком в разделе [Releases](https://github.com/ZzEdovec/onlinefix-linux/releases).

## 🏗 Сборка из исходного кода

Для сборки лаунчера вам понадобится [DevelNext](https://develnext.org):

1. Откройте DevelNext
2. Клонируйте репозиторий в любую папку на вашем диске:
   ```bash
   git clone https://github.com/ZzEdovec/onlinefix-linux
   ```
3. Откройте файл `.dnproject` в DevelNext
4. Нажмите кнопку сборки в верхней части окна

После сборки вы получите исполняемый файл лаунчера.
