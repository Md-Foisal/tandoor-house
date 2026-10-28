<?php

/*
| Seed menu for the "modern-indian" cuisine. Prices in pence.
| Keys per dish: name, description, price, kcal, spice (0-3), veg, vegan,
| allergens, station, prep, hot, special, variants, modifiers.
*/

return [
    [
        'name' => 'Small Plates',
        'description' => 'To share, or not. Order two or three for the table while you decide.',
        'items' => [
            ['name' => 'Bhel Puri', 'description' => 'Puffed rice, sev, green mango, tamarind and mint, tossed at the pass so it stays crisp.', 'price' => 650, 'kcal' => 310, 'spice' => 1, 'veg' => true, 'vegan' => true, 'allergens' => ['gluten', 'peanuts'], 'station' => 'cold', 'prep' => 5, 'hot' => false],
            ['name' => 'Pani Puri', 'description' => 'Six hollow puris, spiced potato and chickpea, with a jug of cold tamarind and mint water to pour yourself.', 'price' => 700, 'kcal' => 280, 'spice' => 2, 'veg' => true, 'vegan' => true, 'allergens' => ['gluten'], 'station' => 'cold', 'prep' => 5, 'hot' => false],
            ['name' => 'Samosa Chaat', 'description' => 'Two broken Punjabi samosas under chickpea curry, yoghurt, tamarind and pomegranate.', 'price' => 750, 'kcal' => 520, 'spice' => 1, 'veg' => true, 'allergens' => ['gluten', 'milk'], 'station' => 'tawa', 'prep' => 8],
            ['name' => 'Onion Bhaji', 'description' => 'Red onion in a light gram-flour batter, ajwain and curry leaf. Served with coriander chutney.', 'price' => 600, 'kcal' => 340, 'spice' => 1, 'veg' => true, 'vegan' => true, 'allergens' => [], 'station' => 'tawa', 'prep' => 7],
            ['name' => 'Chilli Cheese Toast', 'description' => 'Bombay café classic: sourdough, green chilli, mature cheddar and coriander, grilled until it bubbles.', 'price' => 650, 'kcal' => 450, 'spice' => 2, 'veg' => true, 'allergens' => ['gluten', 'milk'], 'station' => 'tawa', 'prep' => 6, 'special' => true],
            ['name' => 'Prawn Koliwada', 'description' => 'Crisp-fried king prawns in a Koli fisherfolk batter, lime and a smoked chilli mayonnaise.', 'price' => 950, 'kcal' => 390, 'spice' => 2, 'allergens' => ['crustaceans', 'eggs', 'mustard'], 'station' => 'tawa', 'prep' => 8],
        ],
    ],
    [
        'name' => 'From the Tandoor',
        'description' => 'Our clay oven runs at 480°C. Marinades rest overnight; everything is cooked to order.',
        'items' => [
            ['name' => 'Chicken Tikka', 'description' => 'Thigh, not breast, in hung yoghurt, Kashmiri chilli and mustard oil. Charred at the edges.', 'price' => 1250, 'kcal' => 420, 'spice' => 1, 'allergens' => ['milk', 'mustard'], 'station' => 'tandoor', 'prep' => 14, 'variants' => [['Half', 1250, 420], ['Full', 1950, 820]]],
            ['name' => 'Lamb Seekh Kebab', 'description' => 'Hand-minced lamb shoulder with ginger, green chilli and mace, cooked on the skewer.', 'price' => 1350, 'kcal' => 480, 'spice' => 2, 'allergens' => [], 'station' => 'tandoor', 'prep' => 14, 'special' => true],
            ['name' => 'Tandoori Lamb Chops', 'description' => 'Three Welsh lamb chops, raw papaya and garam masala, a squeeze of lime at the table.', 'price' => 1850, 'kcal' => 610, 'spice' => 2, 'allergens' => ['milk'], 'station' => 'tandoor', 'prep' => 16],
            ['name' => 'Paneer Shashlik', 'description' => 'Fresh paneer, peppers and red onion in an achari marinade, blistered on the skewer.', 'price' => 1150, 'kcal' => 460, 'spice' => 1, 'veg' => true, 'allergens' => ['milk', 'mustard'], 'station' => 'tandoor', 'prep' => 12],
            ['name' => 'Tandoori Broccoli', 'description' => 'Tenderstem in cashew and green cardamom cream, charred and finished with chaat masala.', 'price' => 900, 'kcal' => 290, 'spice' => 0, 'veg' => true, 'allergens' => ['milk', 'tree_nuts'], 'station' => 'tandoor', 'prep' => 10],
            ['name' => 'Malai Fish Tikka', 'description' => 'Cornish hake in cream cheese, white pepper and green cardamom. Gentle and smoky.', 'price' => 1450, 'kcal' => 380, 'spice' => 0, 'allergens' => ['fish', 'milk'], 'station' => 'tandoor', 'prep' => 12],
        ],
    ],
    [
        'name' => 'Curries',
        'description' => 'Slow-cooked bases made each morning. Ask us to take the heat up or down.',
        'items' => [
            ['name' => 'House Black Daal', 'description' => 'Whole black urad cooked overnight on the dying tandoor, finished with butter and cream.', 'price' => 950, 'kcal' => 510, 'spice' => 0, 'veg' => true, 'allergens' => ['milk'], 'station' => 'curry', 'prep' => 6, 'variants' => [['Regular', 950, 510], ['Large', 1350, 760]]],
            ['name' => 'Butter Chicken', 'description' => 'Tandoori chicken thigh in a tomato, fenugreek and butter makhani. Mild and rich.', 'price' => 1550, 'kcal' => 690, 'spice' => 0, 'allergens' => ['milk', 'tree_nuts'], 'station' => 'curry', 'prep' => 10, 'modifiers' => [['Add extra', 0, 3, [['Extra chicken', 350, []], ['Extra sauce', 200, ['milk', 'tree_nuts']], ['Fresh green chilli', 0, []]]]]],
            ['name' => 'Lamb Rogan Josh', 'description' => 'Kashmiri-style lamb shoulder braised with dried ginger, fennel and ratan jot.', 'price' => 1750, 'kcal' => 640, 'spice' => 2, 'allergens' => ['milk'], 'station' => 'curry', 'prep' => 10],
            ['name' => 'Goan Prawn Curry', 'description' => 'King prawns in coconut, kokum and Kashmiri chilli, sharp and sweet the Goan way.', 'price' => 1750, 'kcal' => 520, 'spice' => 2, 'allergens' => ['crustaceans', 'mustard'], 'station' => 'curry', 'prep' => 10],
            ['name' => 'Chana Masala', 'description' => 'Chickpeas, black cardamom and pomegranate seed powder, the way it is made in Amritsar dhabas.', 'price' => 950, 'kcal' => 430, 'spice' => 1, 'veg' => true, 'vegan' => true, 'allergens' => [], 'station' => 'curry', 'prep' => 6],
            ['name' => 'Chicken Chettinad', 'description' => 'Tamil Nadu pepper curry with stone flower, star anise and roasted coconut. Properly hot.', 'price' => 1550, 'kcal' => 580, 'spice' => 3, 'allergens' => ['mustard'], 'station' => 'curry', 'prep' => 10, 'special' => true],
            ['name' => 'Palak Paneer', 'description' => 'Paneer in a bright spinach purée with garlic, fenugreek and a little cream.', 'price' => 1250, 'kcal' => 540, 'spice' => 1, 'veg' => true, 'allergens' => ['milk'], 'station' => 'curry', 'prep' => 8],
        ],
    ],
    [
        'name' => 'Biryani & Rice',
        'description' => 'Biryanis are sealed with dough and cooked dum in the pot they arrive in.',
        'items' => [
            ['name' => 'Hyderabadi Lamb Biryani', 'description' => 'Aged basmati and lamb on the bone, saffron, rose and crisp onions, sealed and slow-cooked.', 'price' => 1850, 'kcal' => 890, 'spice' => 2, 'allergens' => ['gluten', 'milk', 'tree_nuts'], 'station' => 'curry', 'prep' => 18, 'modifiers' => [['Raita', 0, 1, [['Burani raita', 250, ['milk']], ['Cucumber and mint raita', 250, ['milk']]]]]],
            ['name' => 'Jackfruit Biryani', 'description' => 'Young jackfruit, whole spices and mint under a pastry lid. Vegan.', 'price' => 1450, 'kcal' => 720, 'spice' => 1, 'veg' => true, 'vegan' => true, 'allergens' => ['gluten'], 'station' => 'curry', 'prep' => 18],
            ['name' => 'Steamed Basmati', 'description' => 'Aged basmati, cooked plain.', 'price' => 350, 'kcal' => 260, 'spice' => 0, 'veg' => true, 'vegan' => true, 'allergens' => [], 'station' => 'curry', 'prep' => 2],
            ['name' => 'Jeera Rice', 'description' => 'Basmati with cumin tempered in ghee.', 'price' => 400, 'kcal' => 310, 'spice' => 0, 'veg' => true, 'allergens' => ['milk'], 'station' => 'curry', 'prep' => 3],
            ['name' => 'Lemon Rice', 'description' => 'South Indian rice with mustard seed, curry leaf, peanuts and turmeric.', 'price' => 450, 'kcal' => 340, 'spice' => 1, 'veg' => true, 'vegan' => true, 'allergens' => ['peanuts', 'mustard'], 'station' => 'curry', 'prep' => 3],
        ],
    ],
    [
        'name' => 'Breads',
        'description' => 'Slapped onto the tandoor wall the moment you order.',
        'items' => [
            ['name' => 'Butter Naan', 'description' => 'Leavened, blistered and brushed with butter.', 'price' => 350, 'kcal' => 320, 'spice' => 0, 'veg' => true, 'allergens' => ['gluten', 'milk', 'eggs'], 'station' => 'tandoor', 'prep' => 4],
            ['name' => 'Garlic and Coriander Naan', 'description' => 'Roast garlic, coriander and nigella seed.', 'price' => 400, 'kcal' => 350, 'spice' => 0, 'veg' => true, 'allergens' => ['gluten', 'milk', 'eggs'], 'station' => 'tandoor', 'prep' => 4],
            ['name' => 'Cheese and Chilli Kulcha', 'description' => 'Stuffed with cheddar, green chilli and red onion.', 'price' => 500, 'kcal' => 460, 'spice' => 2, 'veg' => true, 'allergens' => ['gluten', 'milk', 'eggs'], 'station' => 'tandoor', 'prep' => 5],
            ['name' => 'Tandoori Roti', 'description' => 'Wholewheat, unleavened, nothing added. Vegan.', 'price' => 300, 'kcal' => 210, 'spice' => 0, 'veg' => true, 'vegan' => true, 'allergens' => ['gluten'], 'station' => 'tandoor', 'prep' => 3],
            ['name' => 'Laccha Paratha', 'description' => 'Flaky, many-layered wholewheat bread with ghee.', 'price' => 400, 'kcal' => 380, 'spice' => 0, 'veg' => true, 'allergens' => ['gluten', 'milk'], 'station' => 'tandoor', 'prep' => 4],
        ],
    ],
    [
        'name' => 'Sides & Chutneys',
        'description' => 'The bits that make the meal.',
        'items' => [
            ['name' => 'Kachumber Salad', 'description' => 'Cucumber, tomato, red onion, lime and chaat masala.', 'price' => 400, 'kcal' => 70, 'spice' => 0, 'veg' => true, 'vegan' => true, 'allergens' => [], 'station' => 'cold', 'prep' => 3, 'hot' => false],
            ['name' => 'Bombay Potatoes', 'description' => 'Crushed new potatoes with mustard seed, curry leaf and turmeric.', 'price' => 550, 'kcal' => 290, 'spice' => 1, 'veg' => true, 'vegan' => true, 'allergens' => ['mustard'], 'station' => 'tawa', 'prep' => 6],
            ['name' => 'Cucumber Raita', 'description' => 'Thick yoghurt, cucumber, roast cumin and mint.', 'price' => 350, 'kcal' => 110, 'spice' => 0, 'veg' => true, 'allergens' => ['milk'], 'station' => 'cold', 'prep' => 2, 'hot' => false],
            ['name' => 'Poppadoms and Pickles', 'description' => 'Two poppadoms, house lime pickle, mango chutney and mint sauce.', 'price' => 400, 'kcal' => 220, 'spice' => 1, 'veg' => true, 'allergens' => ['mustard', 'milk'], 'station' => 'cold', 'prep' => 2, 'hot' => false],
        ],
    ],
    [
        'name' => 'Desserts',
        'description' => 'Small and sweet, with a cup of chai if you have room.',
        'items' => [
            ['name' => 'Gulab Jamun', 'description' => 'Two warm milk dumplings in rose and cardamom syrup, with clotted cream.', 'price' => 650, 'kcal' => 420, 'spice' => 0, 'veg' => true, 'allergens' => ['milk', 'gluten', 'tree_nuts'], 'station' => 'cold', 'prep' => 4],
            ['name' => 'Pistachio Kulfi', 'description' => 'Slow-reduced milk ice on a stick, rolled in crushed pistachio.', 'price' => 600, 'kcal' => 310, 'spice' => 0, 'veg' => true, 'allergens' => ['milk', 'tree_nuts'], 'station' => 'cold', 'prep' => 2, 'hot' => false],
            ['name' => 'Mango Shrikhand', 'description' => 'Hung yoghurt with Alphonso mango, saffron and a cardamom shortbread.', 'price' => 650, 'kcal' => 360, 'spice' => 0, 'veg' => true, 'allergens' => ['milk', 'gluten', 'eggs'], 'station' => 'cold', 'prep' => 2, 'hot' => false, 'special' => true],
        ],
    ],
    [
        'name' => 'Drinks',
        'description' => 'Lassis and chai made here. A short list of beer and wine chosen to go with spice.',
        'items' => [
            ['name' => 'Mango Lassi', 'description' => 'Alphonso mango, yoghurt and a pinch of cardamom.', 'price' => 450, 'kcal' => 240, 'spice' => 0, 'veg' => true, 'allergens' => ['milk'], 'station' => 'bar', 'prep' => 2, 'hot' => false],
            ['name' => 'Masala Chai', 'description' => 'Assam simmered with milk, ginger, cardamom and clove.', 'price' => 350, 'kcal' => 120, 'spice' => 0, 'veg' => true, 'allergens' => ['milk'], 'station' => 'bar', 'prep' => 3, 'modifiers' => [['Milk', 1, 1, [['Whole milk', 0, ['milk']], ['Oat milk', 0, ['gluten']]]]]],
            ['name' => 'Fresh Lime Soda', 'description' => 'Sweet, salted or both. Our best answer to a Chettinad.', 'price' => 400, 'kcal' => 90, 'spice' => 0, 'veg' => true, 'vegan' => true, 'allergens' => [], 'station' => 'bar', 'prep' => 2, 'hot' => false, 'variants' => [['Sweet', 400, 90], ['Salted', 400, 10], ['Sweet and salted', 400, 60]]],
            ['name' => 'Indian Pale Ale', 'description' => 'A crisp lager-style IPA from a Bengaluru brewery. 330 ml, 4.8% ABV.', 'price' => 550, 'kcal' => 150, 'spice' => 0, 'veg' => true, 'vegan' => true, 'allergens' => ['gluten'], 'station' => 'bar', 'prep' => 1, 'hot' => false],
        ],
    ],
];
