-- 1) Delete all existing categories (this removes all rows from the table)
DELETE FROM payment_categories;

-- 2) Insert the 4 default items once
INSERT INTO payment_categories (name, description, amount, is_recurring, frequency) VALUES
('Tuition Fee', 'Monthly tuition fee', 5000.00, TRUE, 'monthly'),
('Registration Fee', 'One-time registration fee', 2000.00, FALSE, 'one_time'),
('Books', 'Books and learning materials', 1500.00, FALSE, 'one_time'),
('Uniform', 'School uniform', 1200.00, FALSE, 'one_time');
