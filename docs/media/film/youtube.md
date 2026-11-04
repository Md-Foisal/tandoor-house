# YouTube upload

Upload the 4K master (`tandoor-house-film-4k-master.mp4`, 150 MB, kept outside the repository because of GitHub's file size limit). It has less compression than the 87 MB repository copy, so YouTube gets a cleaner source.

## Title

Tandoor House: one evening of service, on a restaurant platform built in Laravel and React

## Description

One real order, followed from a guest's phone to the kitchen display, out with a rider and into the owner's reports.

Tandoor House is a full restaurant platform I designed and built for a fictional modern Indian restaurant in East London. Guests order, book and pay. Staff take, cook, serve and deliver. The owner runs the menu, stock, people and money, and the daily routine runs on its own, each automation with its own switch.

Everything in this film is the running app. Nothing is mocked:
00:00 Opening
00:13 The guest: menu, allergens, basket, checkout
00:40 The pass: order board and kitchen display, live
00:58 The road: a rider delivers
01:11 The table: QR ordering, splitting the bill, and a booking
01:39 The owner: reports, stock, automations
01:58 The details: light and dark, motion
02:06 The build

Stack: Laravel 13, Inertia 3, React 19, TypeScript strict, Tailwind 4, Laravel Reverb, queued jobs, Pest (2,508 tests).

To be clear about the demo: the restaurant is fictional and the data is seeded. Food photos are free-licence stock. Payments run in test mode on a built-in test gateway, with Stripe's Payment Element ready for real keys. Text messages use a log driver until a provider is connected.

Code: https://github.com/Md-Foisal/tandoor-house

Music: "Smooth Jazz" by Francisco Alvear, from Mixkit (Mixkit Stock Music Free License).

## Tags

laravel, react, inertia, typescript, tailwind, laravel reverb, restaurant software, full stack developer, portfolio

## Thumbnail

`poster.jpg`
