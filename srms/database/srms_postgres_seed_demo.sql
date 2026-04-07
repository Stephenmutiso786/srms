BEGIN;
INSERT INTO tbl_division_system (division, min, max, min_point, max_point, points) VALUES
('0', 0, 29, 34, 35, 5),
('1', 75, 100, 7, 17, 1),
('2', 65, 74, 18, 21, 2),
('3', 45, 64, 22, 25, 3),
('4', 30, 44, 26, 33, 4);
INSERT INTO tbl_grade_system (id, name, min, max, remark) VALUES
(1, 'A', 75, 100, 'Excellent'),
(2, 'B', 65, 74, 'Very Good'),
(3, 'C', 45, 64, 'Good'),
(4, 'D', 30, 44, 'Satisfactory'),
(5, 'F', 0, 29, 'Fail');
INSERT INTO tbl_school (id, name, logo, result_system, allow_results) VALUES
(1, 'ELIMU HUB', 'school_logo1711003619.png', 1, 1);
INSERT INTO tbl_staff (id, fname, lname, gender, email, password, level, status) VALUES
(1, 'Bwire', 'Mashauri', 'Male', 'bmashauri704@gmail.com', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 0, 1),
(3, 'ABDUL', 'SHABAN', 'Male', 'abdul@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(4, 'COLLINS', 'MPAGAMA', 'Male', 'collins@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(5, 'DAVID', 'OMAO', 'Male', 'david@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(6, 'DENIS', 'MWAMBUNGU', 'Male', 'denis@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(7, 'ERICK', 'LUOGA', 'Male', 'erick@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(8, 'FARAJI', 'FARAJI', 'Male', 'faraji@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(9, 'FATMA', 'BAHADAD', 'Female', 'fatma@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(10, 'FRANCIS', 'MASANJA', 'Male', 'francis@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(11, 'GLADNESS ', 'PHILIPO', 'Female', 'gladness@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(12, 'GRATION', 'GRATION', 'Male', 'gration@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(13, 'HANS', 'UISSO', 'Male', 'hans@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(14, 'HANSON', 'MAITA', 'Male', 'hanson@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(15, 'HENRY', 'GOWELLE', 'Male', 'henry@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(16, 'HILDA', 'KANDAUMA', 'Female', 'hilda@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(17, 'INNOCENT', 'MBAWALA', 'Male', 'innocent@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(18, 'JAMALI', 'NZOTA', 'Male', 'jamali@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(19, 'JAMIL', 'ABDALLAH', 'Male', 'jamil@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(20, 'JOAN', 'NKYA', 'Female', 'joan@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(21, 'JOSEPH', 'HAMISI', 'Male', 'joseph@srms.test', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 2, 1),
(23, 'Bwire', 'Mashauri', 'Male', 'bwiremunyweki@gmail.com', '$2y$10$l8XYJDrBHTyeZkpupiRhwey6jJihzku0bYXiVtBM5kDRz3sZvSpgC', 1, 1);
INSERT INTO tbl_subjects (id, name) VALUES
(3, 'Mathematics'),
(4, 'English'),
(5, 'Kiswahili'),
(6, 'Geography'),
(7, 'History'),
(8, 'Civics'),
(9, 'Biology'),
(10, 'Physics'),
(11, 'Chemistry'),
(12, 'Literature'),
(15, 'Computer Studies');
COMMIT;
