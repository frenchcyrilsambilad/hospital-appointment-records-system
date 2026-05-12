USE hospital_db;

INSERT INTO departments (dept_name, description)
SELECT 'Cardiology', 'Heart and cardiovascular care, including chest pain, hypertension, ECG review, and follow-up for chronic cardiac conditions'
WHERE NOT EXISTS (SELECT 1 FROM departments WHERE dept_name = 'Cardiology');

UPDATE departments
SET description = 'Heart and cardiovascular care, including chest pain, hypertension, ECG review, and follow-up for chronic cardiac conditions'
WHERE dept_name = 'Cardiology';

INSERT INTO departments (dept_name, description)
SELECT 'Neurology', 'Brain, spinal cord, nerve, and movement disorder care, including headaches, seizures, stroke follow-up, and neuropathy concerns'
WHERE NOT EXISTS (SELECT 1 FROM departments WHERE dept_name = 'Neurology');

UPDATE departments
SET description = 'Brain, spinal cord, nerve, and movement disorder care, including headaches, seizures, stroke follow-up, and neuropathy concerns'
WHERE dept_name = 'Neurology';

INSERT INTO departments (dept_name, description)
SELECT 'Pediatrics', 'Primary and preventive care for infants, children, and adolescents, including wellness visits, vaccines, fever, asthma, and growth concerns'
WHERE NOT EXISTS (SELECT 1 FROM departments WHERE dept_name = 'Pediatrics');

INSERT INTO departments (dept_name, description)
SELECT 'Orthopedics', 'Bone, joint, muscle, and sports injury care, including fractures, arthritis, back pain, sprains, and mobility concerns'
WHERE NOT EXISTS (SELECT 1 FROM departments WHERE dept_name = 'Orthopedics');

INSERT INTO departments (dept_name, description)
SELECT 'Dermatology', 'Skin, hair, and nail care, including rashes, acne, eczema, suspicious lesions, allergies, and minor skin procedures'
WHERE NOT EXISTS (SELECT 1 FROM departments WHERE dept_name = 'Dermatology');

INSERT INTO departments (dept_name, description)
SELECT 'Obstetrics and Gynecology', 'Women''s health services, including prenatal care, menstrual concerns, contraception counseling, pelvic pain, and routine exams'
WHERE NOT EXISTS (SELECT 1 FROM departments WHERE dept_name = 'Obstetrics and Gynecology');

INSERT INTO departments (dept_name, description)
SELECT 'Internal Medicine', 'Adult primary care and chronic disease management, including diabetes, hypertension, infections, preventive screening, and medication review'
WHERE NOT EXISTS (SELECT 1 FROM departments WHERE dept_name = 'Internal Medicine');

INSERT INTO departments (dept_name, description)
SELECT 'Emergency Medicine', 'Urgent assessment and stabilization for acute illness or injury, including severe pain, breathing difficulty, trauma, and sudden symptoms'
WHERE NOT EXISTS (SELECT 1 FROM departments WHERE dept_name = 'Emergency Medicine');

INSERT INTO departments (dept_name, description)
SELECT 'Ophthalmology', 'Eye and vision care, including blurred vision, eye pain, infections, glaucoma screening, cataract evaluation, and diabetic eye concerns'
WHERE NOT EXISTS (SELECT 1 FROM departments WHERE dept_name = 'Ophthalmology');

INSERT INTO departments (dept_name, description)
SELECT 'ENT', 'Ear, nose, and throat care, including sinus problems, sore throat, hearing concerns, ear infections, allergies, and voice issues'
WHERE NOT EXISTS (SELECT 1 FROM departments WHERE dept_name = 'ENT');

INSERT INTO departments (dept_name, description)
SELECT 'Psychiatry', 'Mental health assessment and treatment, including anxiety, depression, sleep problems, medication follow-up, and stress-related concerns'
WHERE NOT EXISTS (SELECT 1 FROM departments WHERE dept_name = 'Psychiatry');

INSERT INTO departments (dept_name, description)
SELECT 'Radiology', 'Diagnostic imaging services and interpretation, including X-ray, ultrasound, CT, MRI coordination, and image-guided consultation'
WHERE NOT EXISTS (SELECT 1 FROM departments WHERE dept_name = 'Radiology');
