# Dice Roller MyCode Plugin for MyBB
Roll dice in a variety of exciting ways using MyCode!

## Features
* Multiple syntaxes, including low-high, NdS, and weighted lists -- including offsets!
* Customizable templates for each component
* Enable or disable sum of NdS rolls
* Add aliases as shorthand for complicated rolls
* Add unique result messages and the range of sums at which they display for each roll
* Add unique resource lists to display an item from for each die in a roll
* Tamper proof! Rolls cannot be edited to abuse RNG or cherry picked.

## Preview
![Example Roll](http://i.imgur.com/adUxPzw.png)
![Example Settings](http://i.imgur.com/EYxWrPX.png)

## Syntax
* [roll=low-high]
  * Rolls a single dice with values between low and high, inclusive.
  * e.g. [roll=1-10]
  * *potential output* - Rolling 1-10: 5
* [roll=low-high+/-F]
  * Rolls a single dice with values between low and high, inclusive. Adds or subtracts the given offset F.
  * e.g. [roll=1-10+5]
  * *potential output*  - Rolling 1-10: 2 + 5 = 7
  * [roll=1-10-5]
  * *potential output* - Rolling 1-10: 2 - 5 = -3
* [roll=dS]
  * Rolls a single dice with with values between 1 and S, inclusive.
  * e.g. [roll=d6]
  * *potential output* - Rolling d6: 4
* [roll=NdS]
  * Rolls N dice with values between 1 and S, inclusive.
  * e.g. [roll=3d6]
  * *potential output* - Rolling 3d6: 4 + 2 + 6 = 12
* [roll=dS+/F]
  * Rolls a single dice with values between low and high, inclusive. Adds or subtracts the given offset F.
  * e.g. [roll=d6+5]
  * *potential output* - Rolling d6+5: 4 + 5 = 9
* [roll=NdS+/-F]
  * Rolls N dice with values between 1 and S, inclusive. Adds or subtracts the given offset F.
  * e.g. [roll=3d6+20]
  * *potential output* - Rolling 3d6+20: 4 + 1 + 6 + 20 = 31
* [roll=weighted list]
  * Rolls a single dice with as many values as there are items in weighted list. Each item in weighted list represents how likely that index is to be selected.
  * e.g. [roll=25,25,50] gives 1 a 25% chance to be rolled, 2 a 25% chance, and 3 a 50% chance.
  * *potential output* - Rolling 25,25,50: 3

## Known Issues
* Cannot preview rolls.
  * This is unlikely to be added due to the potential for abuse and incompatibility with current tamper preventions. (The post ID is used as an RNG seed. Previewed posts have no seed!)
* Cannot quote rolls and maintain their values.
  * Work in progress!
