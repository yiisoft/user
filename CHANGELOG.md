# Yii User Change Log

## 2.2.1 under development

- Chg #101: Rename `UserAuth` to `WebAuth`, mark `UserAuth` as deprecated (@olegbaturin)
- New #101: Add `ApiAuth` authentication method (@olegbaturin)

## 2.2.0 May 07, 2024

- Chg #96: Raise minimum PHP version to 8.1 (@vjik)
- Chg #98: Change log level from `warning` to `debug` for `LoginMiddleware` (@viktorprogger)
- Enh #90: Add support for `psr/http-message` version `^2.0` (@vjik)
- Enh #93: Allow to use backed enumerations as permission name in `CurrentUser::can()` method (@vjik)

## 2.1.0 December 05, 2023

- New #86: Add optional parameter `$duration` to `CookieLogin::addCookie()` (@vjik)
- Chg #58: Raise the minimum version of PHP to 8.0 and did refactoring using the features of it (@xepozz, @rustamwin)
- Chg #58: Raise version of `yiisoft/access` to `^2.0` (@rustamwin)
- Chg #71: Add token logging when login was failed (@xepozz)
- Enh #83: Allow to create a session cookie via `CookieLogin` (@rustamwin)

## 2.0.0 February 15, 2023

- Chg #67: Adapt configuration group names to Yii conventions (@vjik)
- Enh #68: Add support of `yiisoft/session` version `^2.0` (@vjik)

## 1.0.1 June 15, 2022

- Enh #55: Add support for `2.0`, `3.0` versions of `psr/log` (@rustamwin)

## 1.0.0 December 22, 2021

- Initial release.
