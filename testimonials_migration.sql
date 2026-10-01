-- Testimonials table for patient reviews
CREATE TABLE IF NOT EXISTS `testimonials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_name` varchar(100) NOT NULL,
  `rating` tinyint(1) NOT NULL DEFAULT 5,
  `comment` text NOT NULL,
  `is_approved` tinyint(1) NOT NULL DEFAULT 0,
  `submitted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert the existing static testimonials as approved
INSERT INTO `testimonials` (`patient_name`, `rating`, `comment`, `is_approved`) VALUES
('Ramil Nardo', 5, 'Awesome doctor. She explains everything well and set your expectation before the process. The tooth extraction is also very cheap and my health card covered both cleaning and 2 pasta.', 1),
('Marvelous Advincula', 5, 'The doc is good at explaining things regarding teeth and it\'s easy to use my health card here. I just need to schedule days for the appointment so the process goes quickly.', 1),
('いいえ', 5, 'Very professional, good dentist, very light-handed and affordable', 1);
