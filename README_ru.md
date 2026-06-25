[English](https://github.com/ZzEdovec/onlinefix-linux/blob/main/README.md) | [Русский](https://github.com/ZzEdovec/onlinefix-linux/blob/main/README_ru.md)

![Окно OFME](https://zzedovec.github.io/images/ofmeBanner.png)

# OnlineFix Linux Launcher
**Простой и удобный лаунчер для запуска игр с пользовательскими исправлениями мультиплеера Linux**
## ✨ Возможности
- Запуск игр без необходимости вручную настраивать `WINEDLLOVERRIDES` и другие параметры
- Автоматическое получение обложек игр из Steam
- Оверлей Steam
- Автоматическая установка игр с OnlineFix и FreeTP
- Специфичные патчи для некоторых типов фиксов
- Автоматическое извлечение иконок из игр
- Создание ярлыков на рабочем столе и в меню приложений для игр
- Скачивание игр из лаунчера (нужна установленная `aria2` и источник OnlineFix от Hydra Launcher
## ❕ Совместимость
- SteamFix
    - OnlineFix - полная поддержка 64-битных, 32-битные могут иметь проблемы
    - FreeTP - только фиксы старее 2026 года
- Кастомные сервера OnlineFix (Photon Launcher)
    - Полная поддержка
- SteamFix и EOSFix (совмещенные)
    - OnlineFix - полная поддержка
    - FreeTP - полностью сломано
- EOSFix
    - OnlineFix - с EOSAuthHooker, старый тип не тестировался
    - FreeTP - полностью сломано
## 📦 Зависимости
Перед использованием лаунчера убедитесь, что установлены следующие пакеты:
- `ffmpeg`
- `steam`
- `icoextract` (необязательно) - для лучшего извлечения иконок из .exe
- `aria2` (необязательно) - для скачивания игр

‼️ Они должны быть установлены как нормальные пакеты. Flatpak и Snap версии **не поддерживаются и не будут!** В случае их использования лаунчер не будет правильно работать - и это не вина его разработчика.
## ⬇️ Установка
Если вы используете Arch Linux или основанный на нём дистрибутив, установите из AUR пакет [onlinefix-linux-launcher-bin](https://aur.archlinux.org/packages/onlinefix-linux-launcher-bin) (например, yay -S onlinefix-linux-launcher-bin).
При использовании иного дистрибутива используйте установщик из раздела [Releases](https://github.com/ZzEdovec/onlinefix-linux/releases).
## 🏗 Сборка из исходного кода
Для сборки лаунчера вам понадобится [DevelNext](https://develnext.org):
1. Откройте DevelNext
2. Клонируйте репозиторий в любую папку на вашем диске:
```bash
git clone https://github.com/ZzEdovec/onlinefix-linux
```
3. Откройте файл `.dnproject` в DevelNext
4. Покажется сообщение об отсутствующих зависимостях, установите их во вкладке `Проект > Пакеты`:
	- [jphp-animatefx-ext](https://github.com/jphp-group/jphp-animatefx-ext/releases)
	- [jphp-controlsfx-ext](https://github.com/jphp-group/jphp-controlsfx-ext/releases)
	- [jphp-vdf-ext](https://github.com/GIGNIGHT/jphp-vdf-ext) (требуется ручная компиляция в `dnbundle` через [jppm](https://github.com/jphp-group/jphp/releases))
	- [jphp-websocket-client](https://github.com/jphp-group/jphp-websocket-client/releases)
5. Нажмите кнопку сборки в верхней части окна

После сборки вы получите исполняемый файл лаунчера.
