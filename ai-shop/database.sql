-- AI Shop database (MySQL 8+ / MariaDB compatible) - import via phpMyAdmin
CREATE DATABASE IF NOT EXISTS ecommerce_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ecommerce_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS product_views;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  description TEXT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  old_price DECIMAL(10,2) NULL,
  image VARCHAR(255) NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  stock INT UNSIGNED NOT NULL DEFAULT 0,
  rating DECIMAL(2,1) NOT NULL DEFAULT 0.0,
  tags VARCHAR(500) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_products_category (category_id),
  INDEX idx_products_rating (rating),
  INDEX idx_products_price (price),
  INDEX idx_products_name (name),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cart (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_cart_user_product (user_id, product_id),
  INDEX idx_cart_product (product_id),
  CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_cart_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  total DECIMAL(10,2) NOT NULL,
  status ENUM('Pending','Processing','Shipped','Delivered','Cancelled') DEFAULT 'Pending',
  address TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_orders_user (user_id),
  INDEX idx_orders_status (status),
  INDEX idx_orders_created (created_at),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  INDEX idx_oi_order (order_id),
  INDEX idx_oi_product (product_id),
  CONSTRAINT fk_oi_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_views (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  session_id VARCHAR(100) NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pv_user (user_id),
  INDEX idx_pv_session (session_id),
  INDEX idx_pv_product (product_id),
  INDEX idx_pv_viewed (viewed_at),
  CONSTRAINT fk_pv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_pv_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (id, name, slug) VALUES
(1, 'Electronics', 'electronics'),
(2, 'Fashion', 'fashion'),
(3, 'Books', 'books'),
(4, 'Home', 'home'),
(5, 'Sports', 'sports');

INSERT INTO products (name, description, price, old_price, image, category_id, stock, rating, tags) VALUES
('boAt Rockerz 450 Wireless Bluetooth Headphones', 'On-ear Bluetooth headphones with 40mm drivers, up to 15 hours of playback, padded ear cushions and a built-in mic for calls. Perfect for music, travel and online classes.', 1499.00, 2990.00, 'headphones.jpg', 1, 60, 4.3, 'headphones,wireless,bluetooth,audio,music,earphones,boat,gadget'),
('JBL Go 3 Portable Bluetooth Speaker', 'Pocket-sized waterproof speaker with punchy JBL Pro Sound, 5 hours of playtime and IP67 dust and water protection. Great for trips, parties and the balcony.', 2999.00, 3999.00, 'speaker.jpg', 1, 40, 4.6, 'speaker,bluetooth,wireless,audio,music,portable,waterproof,jbl,gadget'),
('Redmi Note 13 5G Smartphone (8GB RAM, 128GB)', '6.67-inch AMOLED 120Hz display, 108MP camera, 5G connectivity and a 5000mAh battery with fast charging. A powerful all-rounder for work and entertainment.', 17999.00, 21999.00, 'smartphone.jpg', 1, 25, 4.4, 'smartphone,phone,mobile,5g,camera,android,redmi,gadget'),
('Noise ColorFit Pulse Smartwatch', '1.8-inch bright display smartwatch with Bluetooth calling, heart-rate and SpO2 monitoring, 100+ watch faces and 7-day battery life.', 1999.00, 4999.00, 'smartwatch.jpg', 1, 80, 4.2, 'smartwatch,watch,wearable,bluetooth,fitness,health,noise,gadget'),
('Campus Running Shoes for Men', 'Lightweight mesh running shoes with cushioned EVA midsole and anti-skid outsole. Breathable and comfortable for jogging, gym and daily wear.', 1299.00, 2499.00, 'shoes.jpg', 2, 70, 4.1, 'shoes,shoe,running,sports,footwear,men,sneakers,gym,campus'),
('Lavie Women Printed Handbag', 'Spacious faux-leather handbag with zip closure, multiple inner pockets and a detachable sling strap. Ideal for office, college and shopping trips.', 1899.00, 3499.00, 'handbag.jpg', 2, 35, 4.3, 'handbag,bag,women,fashion,purse,lavie,accessories,office'),
('Cotton Round Neck T-Shirt for Men', '100% combed cotton t-shirt with a soft feel and regular fit. Breathable, pre-shrunk fabric that stays comfortable all day. Available in classic colours.', 499.00, 999.00, 'tshirt.jpg', 2, 150, 4.0, 'tshirt,t-shirt,shirt,cotton,men,casual,clothing,fashion'),
('Let Us C by Yashavant Kanetkar', 'The classic beginner-friendly guide to the C programming language, covering fundamentals, pointers, arrays, structures and file handling with plenty of exercises.', 449.00, 599.00, 'book.jpg', 3, 90, 4.5, 'book,programming,coding,c language,computer science,education,study,students'),
('The Alchemist by Paulo Coelho', 'A world-famous fiction novel about Santiago, a shepherd boy who sets out on a journey to find treasure and discovers the meaning of following his dreams.', 299.00, 399.00, 'novel.jpg', 3, 120, 4.7, 'book,novel,fiction,story,paulo coelho,bestseller,reading,inspirational'),
('Philips LED Rechargeable Study Lamp', 'Eye-care LED desk lamp with 3 brightness modes, touch control, flexible neck and a rechargeable battery. Flicker-free light for long study sessions.', 899.00, 1499.00, 'lamp.jpg', 4, 55, 4.4, 'lamp,study,led,desk,light,home,rechargeable,philips,students'),
('Prestige Iris 750W Mixer Grinder with 3 Jars', 'Powerful 750W motor with 3 stainless steel jars for wet grinding, dry grinding and chutney. Overload protection and sturdy anti-skid feet for Indian kitchens.', 3499.00, 5295.00, 'mixer.jpg', 4, 30, 4.5, 'kitchen,mixer,grinder,appliance,home,cooking,prestige,kitchen appliance'),
('SG Kashmir Willow Cricket Bat', 'Full-size Kashmir willow cricket bat with a thick edge profile and a comfortable grip. Suitable for tennis-ball and leather-ball practice and club matches.', 2199.00, 3200.00, 'cricket-bat.jpg', 5, 20, 4.2, 'cricket,bat,sports,willow,sg,outdoor,training,kashmir willow'),
('Boldfit Anti-Skid Yoga Mat 6mm', 'Extra-thick 6mm NBR yoga mat with a non-slip texture and a carry strap. Cushions joints during yoga, stretching and floor workouts.', 599.00, 1299.00, 'yoga-mat.jpg', 5, 100, 4.3, 'yoga,mat,fitness,exercise,workout,sports,gym,home workout'),
('Fastrack Reflex Fitness Band Watch', 'Slim fitness tracker with step counter, heart-rate monitor, sleep tracking, multiple sports modes and a 10-day battery. Water resistant for workouts.', 2499.00, 3995.00, 'fitness-watch.jpg', 5, 45, 4.1, 'fitness,watch,band,tracker,health,wearable,fastrack,sports,smartwatch');
