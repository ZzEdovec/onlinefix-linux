[English](https://github.com/ZzEdovec/onlinefix-linux/blob/main/README.md) | [Русский](https://github.com/ZzEdovec/onlinefix-linux/blob/main/README_ru.md)

![Окно OFME](https://zzedovec.github.io/images/ofmeBanner.png)
# OnlineFix Linux Launcher

**Простой и удобный лаунчер для запуска игр с ****[online-fix.me](https://online-fix.me)**** на Linux**

## ✨ Возможности

- Запуск игр без необходимости вручную настраивать `WINEDLLOVERRIDES` и другие параметры
- Автоматическое получение обложек игр из Steam
- Загрузка иконок игр
- Создание ярлыков на рабочем столе и в меню приложений для игр

## ❕ Совместимость

В настоящее время поддерживаются большинство онлайн-фиксов с online-fix.me. 
Фиксы, включающие пользовательские лаунчеры (например, Phasmophobia), пока не тестировались.

В разработке:
- Фиксы для Epic Games
- Steam-фиксы с freetp.org

## 📦 Зависимости

Перед использованием лаунчера убедитесь, что установлены следующие пакеты:

- `protontricks`
- `ffmpeg`
- `7zip`

### Установка зависимостей:

#### SteamOS / Steam Deck:

1. Отключите режим только для чтения файловой системы:
   ```bash
   sudo steamos-readonly disable
   ```
2. Отредактируйте файл `/etc/pacman.conf` и установите `SigLevel = TrustAll`
   - **Предупреждение:** Использование `TrustAll` отключает проверку подписи пакетов, что может представлять угрозу безопасности. Однако без этого изменения `pacman` не работает корректно на SteamOS.
   - Вы можете использовать `nemo` или `kate` для редактирования файла:
     ```bash
     sudo nemo /etc/pacman.conf
     ```
     или
     ```bash
     sudo kate /etc/pacman.conf
     ```
3. Включите репозиторий **Chaotic AUR**, следуя [официальной инструкции](https://aur.chaotic.cx/docs)
4. Установите необходимые зависимости:
   ```bash
   sudo pacman -Sy protontricks-git p7zip
   ```
5. После установки рекомендуется снова включить режим только для чтения:
   ```bash
   sudo steamos-readonly enable
   ```

#### Ubuntu и производные:

```bash
sudo apt install protontricks ffmpeg p7zip-full
```

#### Fedora:

```bash
sudo dnf install protontricks ffmpeg p7zip
```

#### Arch Linux и производные:

Если у вас не установлен `yay`, сначала установите его:

```bash
sudo pacman -S --noconfirm git
git clone https://aur.archlinux.org/yay-bin.git
cd yay-bin
makepkg -si
cd ..
rm -rf yay-bin
```

Затем установите все зависимости с помощью:

```bash
yay -S --noconfirm protontricks ffmpeg 7zip
```

**Или просто скопируйте и выполните эту команду в терминале:**
```bash
sudo pacman -S --noconfirm git && git clone https://aur.archlinux.org/yay-bin.git && cd yay-bin && makepkg -si && cd .. && rm -rf yay-bin && yay -S --noconfirm protontricks ffmpeg 7zip
```

#### Solus:

```bash
sudo eopkg install protontricks ffmpeg p7zip
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
