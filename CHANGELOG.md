# Changelog

All notable changes to this bundle will be documented in this file.

## [1.1.3] - 2024-07-22

- Support for Doctrine ORM 3.*

## [1.1.2] - 2024-07-05

- Changed: Timezonify service now refers to the attribute of entity properties instead of relying to the properties class

## [1.1.1] - 2024-03-28

- New: HexadecimalIdGenerator generates random hexadecimal id for entities
- New: Timezonify service auto-converts datetime from UTC inside the database into a particular timezone inside entities

## [1.1.0] - 2024-01-20

- Support for Symfony 7 and PHP 8.2

## [1.0.1] - 2023-11-13

- Fix: Request body conversion failed if non-image file is submitted to an ImageWrapper property of a RequestBody subclass
- New: TimestampIdGenerator and TimestampIdEntityTrait that simplify using timestamp string (e.g. 20230101123456789012) as the primary key of an entity

## [1.0.0] - 2023-11-06

- First release version
